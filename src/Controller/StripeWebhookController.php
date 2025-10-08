<?php

namespace App\Controller;

use App\Entity\Enrollment;
use App\Entity\Notification;
use App\Entity\Subscription;
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
        $secret    = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? null;

        if (!$secret) {
            return new Response('Webhook secret not configured', 500);
        }

        try {
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY']);
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        }

        // Log pour savoir quel type d’événement Stripe arrive
        $logger->info('Stripe webhook received', ['type' => $event->type]);

        /* ==============================================================
         * 🧾 1. Gestion des abonnements (invoice.payment_succeeded)
         * ============================================================== */
        if ($event->type === 'invoice.payment_succeeded') {
            $invoice = $event->data->object;
            $customerId = $invoice->customer ?? null;
            $subscriptionId = $invoice->subscription ?? null;

            if (!$customerId) {
                $logger->warning('Stripe invoice without customer ID');
                return new Response('Missing customer', 200);
            }

            $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);
            $customer = $stripe->customers->retrieve($customerId);

            $user = $userRepo->findOneBy(['email' => $customer->email ?? null]);
            if (!$user) {
                $logger->warning('Subscription: user not found for Stripe customer', ['email' => $customer->email]);
                return new Response('User not found', 200);
            }

            $subscription = $em->getRepository(Subscription::class)->findOneBy(['user' => $user]);
            if (!$subscription) {
                $subscription = new Subscription();
                $subscription->setUser($user);
            }

            $subscription->setStartDate(new \DateTime());
            $subscription->setEndDate((new \DateTime())->modify('+1 month'));
            $subscription->setType('monthly');
            $subscription->setIsActive(true);
            $subscription->setStripeSubscriptionId($subscriptionId ?? null);

            $em->persist($subscription);
            $em->flush();

            $logger->info('Subscription updated/created', [
                'user' => $user->getEmail(),
                'subscriptionId' => $subscriptionId,
            ]);

            return new Response('Subscription updated', 200);
        }

        /* ==============================================================
         * 💳 2. Gestion des paiements uniques (checkout.session.completed)
         * ============================================================== */
        if ($event->type === 'checkout.session.completed') {
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;

            if (($session->payment_status ?? null) !== 'paid') {
                return new Response('Ignored: not paid', 200);
            }

            $paymentIntentId = is_string($session->payment_intent)
                ? $session->payment_intent
                : ($session->payment_intent->id ?? null);

            $metadata = $session->metadata ?? (object)[];
            $context  = $metadata->context ?? 'employee_purchase';

            $courseId  = $metadata->course_id ?? null;
            $course    = $courseId ? $courseRepo->find($courseId) : null;

            if (!$course) {
                $logger->warning('Webhook: course not found', [
                    'courseId' => $courseId,
                    'sessionId' => $session->id ?? null,
                ]);
                return new Response('Course not found', 200);
            }

            $amount   = isset($session->amount_total) ? $session->amount_total / 100 : null;
            $currency = strtoupper($session->currency ?? 'EUR');

            // === Branche 1 : achat employé ===
            if ($context === 'employee_purchase') {
                $userId = $metadata->user_id ?? null;
                $user = $userId ? $userRepo->find($userId) : null;

                if (!$user && !empty($session->customer_email)) {
                    $user = $userRepo->findOneBy(['email' => $session->customer_email]);
                }

                if (!$user) {
                    $logger->warning('Employee purchase: user not found', [
                        'userId' => $userId,
                        'email'  => $session->customer_email ?? null,
                    ]);
                    return new Response('User not found', 200);
                }

                // Éviter les doublons
                if ($enrollmentRepo->findOneBy(['user' => $user, 'course' => $course])) {
                    return new Response('Enrollment already exists', 200);
                }

                $enrollment = new Enrollment();
                $enrollment->setUser($user);
                $enrollment->setCourse($course);

                if (method_exists($enrollment, 'setStripePaymentIntentId')) {
                    $enrollment->setStripePaymentIntentId($paymentIntentId);
                }
                if (method_exists($enrollment, 'setStripeSessionId')) {
                    $enrollment->setStripeSessionId($session->id ?? null);
                }

                $em->persist($enrollment);
                $em->flush();

                // Envoi facture
                $mailer->send(
                    $user->getEmail(),
                    'Votre facture - ' . $course->getTitle(),
                    'emails/invoice.html.twig',
                    [
                        'user'     => $user,
                        'course'   => $course,
                        'amount'   => $amount,
                        'tva'      => null,
                        'date'     => new \DateTime(),
                        'currency' => $currency,
                    ],
                    'pdf/invoice.html.twig',
                    'facture-' . $course->getId() . '.pdf'
                );

                // Notification
                try {
                    $notificationService->createEntityNotification(
                        $user,
                        '🎉 Payment confirmed!',
                        $enrollment,
                        "Your enrollment in the course \"{$course->getTitle()}\" has been confirmed. ({$amount}€)",
                        Notification::TYPE_SUCCESS,
                        '/app/course/' . $course->getId(),
                        'payment-success',
                        Notification::PRIORITY_HIGH
                    );
                } catch (\Throwable $e) {
                    $logger->error('Failed to create notification', ['error' => $e->getMessage()]);
                }

                return new Response('Enrollment created', 200);
            }

            // === Branche 2 : achat entreprise (1 place) ===
            if ($context === 'company_single_seat') {
                $targetEmail = $metadata->target_email ?? null;
                if (!$targetEmail) {
                    $logger->warning('company_single_seat: target_email missing');
                    return new Response('Missing target_email', 200);
                }

                $employee = $userRepo->findOneBy(['email' => $targetEmail]);

                if ($employee) {
                    if (!$enrollmentRepo->findOneBy(['user' => $employee, 'course' => $course])) {
                        $enrollment = new Enrollment();
                        $enrollment->setUser($employee);
                        $enrollment->setCourse($course);
                        $em->persist($enrollment);
                        $em->flush();

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
                    }
                } else {
                    $mailer->send(
                        $targetEmail,
                        'Your company bought you a course',
                        'emails/company_invite.html.twig',
                        [
                            'course' => $course,
                            'signup_url' => $this->generateUrl('show_register', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                        ]
                    );
                }

                // Facture à l’acheteur
                $buyerEmail = $session->customer_details->email ?? $session->customer_email ?? null;
                if ($buyerEmail) {
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
                }

                return new Response('Processed company_single_seat', 200);
            }

            // === Branche 3 : pack entreprise ===
            if ($context === 'company_pack') {
                $logger->info('company_pack received', [
                    'courseId' => $courseId,
                    'quantity' => (int)($metadata->quantity ?? 1),
                ]);
                return new Response('Processed company_pack', 200);
            }

            // Contexte inconnu
            $logger->warning('Unknown context in checkout', ['context' => $context]);
            return new Response('Unknown context', 200);
        }

        // Si on reçoit un autre event → on l’ignore proprement
        return new Response('Event ignored', 200);
    }
}
