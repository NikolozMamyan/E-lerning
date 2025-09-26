<?php

namespace App\Controller;

use App\Entity\Enrollment;
use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Repository\UserRepository;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StripeWebhookController extends AbstractController
{
    #[Route('/api/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        CourseRepository $courseRepo,
        EnrollmentRepository $enrollmentRepo,
        MailerService $mailer,
        NotificationService $notificationService,
        LoggerInterface $logger
    ): Response {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $secret    = $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

        if (!$secret) {
            return new Response('Webhook secret not configured', 500);
        }

        try {
            \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return new Response('Event ignored', 200);
        }

        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;

        // Sécurité: on ne traite que les paiements effectivement capturés
        if (($session->payment_status ?? null) !== 'paid') {
            return new Response('Ignored: not paid', 200);
        }

        // Idempotence simple: si on stocke session id / payment intent sur Enrollment, on peut ignorer ici
        $paymentIntentId = is_string($session->payment_intent)
            ? $session->payment_intent
            : ($session->payment_intent->id ?? null);

        $metadata = $session->metadata ?? (object)[];
        $context  = $metadata->context ?? 'employee_purchase'; // valeur par défaut: rétro-compat

        // Données communes
        $courseId  = $metadata->course_id ?? null;
        $course    = $courseId ? $courseRepo->find($courseId) : null;
        if (!$course) {
            $logger->warning('Webhook: course not found', ['courseId' => $courseId, 'sessionId' => $session->id ?? null]);
            return new Response('Course not found', 200);
        }

        // Montant (pour facture / notifications)
        $amount = isset($session->amount_total) ? $session->amount_total / 100 : null;
        $currency = strtoupper($session->currency ?? 'EUR');

        // === Branche 1 : paiement employé (ton flux actuel) ====================
        if ($context === 'employee_purchase') {
            $userId = $metadata->user_id ?? null;

            $user = $userId ? $userRepo->find($userId) : null;
            if (!$user && !empty($session->customer_email)) {
                $user = $userRepo->findOneBy(['email' => $session->customer_email]);
            }
            if (!$user) {
                $logger->warning('Webhook employee_purchase: user not found', [
                    'userId' => $userId,
                    'customer_email' => $session->customer_email ?? null,
                    'sessionId' => $session->id ?? null,
                ]);
                return new Response('User not found', 200);
            }

            // Idempotence enrollment
            $existing = $enrollmentRepo->findOneBy(['user' => $user, 'course' => $course]);
            if ($existing) {
                return new Response('Enrollment already exists', 200);
            }

            $enrollment = new Enrollment();
            $enrollment->setUser($user);
            $enrollment->setCourse($course);

            if (property_exists(Enrollment::class, 'stripePaymentIntentId') && method_exists($enrollment, 'setStripePaymentIntentId')) {
                $enrollment->setStripePaymentIntentId($paymentIntentId);
            }
            if (property_exists(Enrollment::class, 'stripeSessionId') && method_exists($enrollment, 'setStripeSessionId')) {
                $enrollment->setStripeSessionId($session->id ?? null);
            }

            $em->persist($enrollment);
            $em->flush();

            // Facture & notification (employé)
            $mailer->send(
                $user->getEmail(),
                'Votre facture - ' . $course->getTitle(),
                'emails/invoice.html.twig',
                [
                    'user'   => $user,
                    'course' => $course,
                    'amount' => $amount,
                    'tva'    => null,
                    'date'   => new \DateTime(),
                    'currency' => $currency,
                ]
            );

            try {
                $notificationService->createEntityNotification(
                    $user,
                    '🎉 Payment confirmed!',
                    $enrollment,
                    "Your enrollment in the course \"{$course->getTitle()}\" has been confirmed. : {$amount}€",
                    Notification::TYPE_SUCCESS,
                    '/app/course/' . $course->getId(),
                    'payment-success',
                    Notification::PRIORITY_HIGH
                );
            } catch (\Throwable $e) {
                $logger->error('Failed to create payment notification (employee)', ['error' => $e->getMessage()]);
            }

            return new Response('Enrollment created (employee)', 200);
        }

        // === Branche 2 : paiement company (1 place) ============================
        if ($context === 'company_single_seat') {
            $companyUserId = $metadata->company_user_id ?? null; // l’admin qui a payé
            $targetEmail   = $metadata->target_email    ?? null; // l’employé à inscrire

            if (!$targetEmail) {
                $logger->warning('Webhook company_single_seat: target_email missing', ['sessionId' => $session->id ?? null]);
                return new Response('target_email missing', 200);
            }

            // On cherche l’employé par email
            $employee = $userRepo->findOneBy(['email' => $targetEmail]);

            if ($employee) {
                // Idempotence: pas de doublon
                $existing = $enrollmentRepo->findOneBy(['user' => $employee, 'course' => $course]);
                if (!$existing) {
                    $enrollment = new Enrollment();
                    $enrollment->setUser($employee);
                    $enrollment->setCourse($course);

                    if (property_exists(Enrollment::class, 'stripePaymentIntentId') && method_exists($enrollment, 'setStripePaymentIntentId')) {
                        $enrollment->setStripePaymentIntentId($paymentIntentId);
                    }
                    if (property_exists(Enrollment::class, 'stripeSessionId') && method_exists($enrollment, 'setStripeSessionId')) {
                        $enrollment->setStripeSessionId($session->id ?? null);
                    }

                    $em->persist($enrollment);
                    $em->flush();

                    // Notif à l’employé (accès activé)
                    try {
                        $notificationService->createEntityNotification(
                            $employee,
                            '👋 You’ve been enrolled',
                            $enrollment,
                            "Your company has assigned you the course \"{$course->getTitle()}\".",
                            Notification::TYPE_INFO,
                            '/app/course/' . $course->getId(),
                            'company-enrollment',
                            Notification::PRIORITY_NORMAL
                        );
                    } catch (\Throwable $e) {
                        $logger->error('Failed to notify employee (company_single_seat)', ['error' => $e->getMessage()]);
                    }
                }
            } else {
                // L’employé n’existe pas encore.
                // Ici deux options:
                // 1) Envoyer une invitation à créer un compte, et mémoriser l’assignation en attente (table PendingAssignment)
                // 2) Se contenter d’envoyer un email d’invitation sans persistence (risque de perte)
                // -> On choisit 1) si tu as l’entité; sinon au moins un email d’invitation:
                try {
                    $mailer->send(
                        $targetEmail,
                        'Your company bought you a course',
                        'emails/company_invite.html.twig',
                        [
                            'course' => $course,
                            'company_email' => $session->customer_details->email ?? null,
                            'signup_url' => $this->generateUrl('show_register', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                        ]
                    );
                } catch (\Throwable $e) {
                    $logger->error('Failed to send invite email', ['targetEmail' => $targetEmail, 'error' => $e->getMessage()]);
                }
            }

            // Facture à l’admin/company (acheteur)
            $buyerEmail = $session->customer_details->email ?? $session->customer_email ?? null;
            if ($buyerEmail) {
                try {
                    $mailer->send(
                        $buyerEmail,
                        'Votre facture - ' . $course->getTitle(),
                        'emails/invoice_company.html.twig',
                        [
                            'amount'   => $amount,
                            'currency' => $currency,
                            'date'     => new \DateTime(),
                            'course'   => $course,
                            'target'   => $targetEmail,
                        ]
                    );
                } catch (\Throwable $e) {
                    $logger->error('Failed to send company invoice', ['error' => $e->getMessage()]);
                }
            }

            return new Response('Processed company_single_seat', 200);
        }

        // === (Optionnel) Branche 3 : paiement company (pack de sièges) ========
        if ($context === 'company_pack') {
            // Ici, au lieu de créer des Enrollment tout de suite,
            // tu peux créer/mettre à jour une entité CompanyCoursePurchase
            // avec remaining_seats = (int)($metadata->quantity ?? 1).
            // Puis, dans ton backoffice company, l’admin assigne des emails un par un
            // en décrémentant remaining_seats.
            // Laisse un log pour te rappeler de l’implémenter si pas encore fait:
            $logger->info('company_pack purchase received', [
                'courseId' => $courseId,
                'quantity' => (int)($metadata->quantity ?? 1),
                'sessionId' => $session->id ?? null,
            ]);
            return new Response('Processed company_pack (stub)', 200);
        }

        // Si on tombe ici, contexte inconnu → on ne casse pas le flux Stripe
        $logger->warning('Unknown context in webhook', ['context' => $context]);
        return new Response('Unknown context', 200);
    }
}
