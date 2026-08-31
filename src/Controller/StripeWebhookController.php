<?php

namespace App\Controller;

use App\Entity\Enrollment;
use App\Entity\Notification;
use App\Entity\Subscription;
use Psr\Log\LoggerInterface;
use App\Service\MailerService;
use App\Repository\UserRepository;
use App\Repository\CourseRepository;
use App\Service\NotificationService;
use App\Service\GuestPurchaseProvisioner;
use App\Service\CompanyCourseCheckoutService;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        SubscriptionRepository $subscriptionRepo,
        MailerService $mailer,
        NotificationService $notificationService,
        GuestPurchaseProvisioner $guestPurchaseProvisioner,
        CompanyCourseCheckoutService $companyCourseCheckoutService,
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

        $logger->info('Stripe webhook received', ['type' => $event->type]);

        /* ==============================================================
         * 🧾 1. Gestion des abonnements (invoice.payment_succeeded)
         * ============================================================== */
        if ($event->type === 'invoice.payment_succeeded') {
            $invoice = $event->data->object;
            $customerId = $invoice->customer ?? null;
            $subscriptionId = $invoice->subscription ?? null;

            $logger->info('🔍 DEBUT invoice.payment_succeeded', [
                'customerId' => $customerId,
                'subscriptionId' => $subscriptionId,
                'invoice_id' => $invoice->id ?? null
            ]);

            if (!$customerId) {
                $logger->warning('❌ Stripe invoice without customer ID');
                return new Response('Missing customer', 200);
            }

            try {
                $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY']);
                $customer = $stripe->customers->retrieve($customerId);

                $customerEmail = $customer->email ?? null;

                $logger->info('📧 Customer Stripe récupéré', [
                    'email' => $customerEmail,
                    'customerId' => $customerId
                ]);

                if (!$customerEmail) {
                    $logger->warning('❌ Customer Stripe sans email');
                    return new Response('Customer has no email', 200);
                }

                $user = $userRepo->findOneBy(['email' => $customerEmail]);

                if (!$user) {
                    $logger->warning('❌ User not found in database', ['email' => $customerEmail]);
                    return new Response('User not found', 200);
                }

                $logger->info('✅ User trouvé', [
                    'userId' => $user->getId(),
                    'email' => $user->getEmail()
                ]);

                $subscription = $subscriptionRepo->findOneBy(['user' => $user]);

                if (!$subscription) {
                    $logger->info('➕ Création nouvelle subscription');
                    $subscription = new Subscription();
                    $subscription->setUser($user);
                } else {
                    $logger->info('🔄 Mise à jour subscription existante', ['id' => $subscription->getId()]);
                }

                // 👇 Abonnement mensuel avec engagement 1 an
                $subscription->setStartDate(new \DateTime());
                $subscription->setEndDate((new \DateTime())->modify('+1 year')); // Engagement 1 an
                $subscription->setType('monthly');
                $subscription->setIsActive(true);

                $logger->info('💾 Avant persist/flush', [
                    'type' => 'monthly',
                    'startDate' => $subscription->getStartDate()->format('Y-m-d H:i:s'),
                    'endDate' => $subscription->getEndDate()->format('Y-m-d H:i:s'),
                ]);

                $em->persist($subscription);
                $em->flush(); // pour garantir ID

                /* ============================================================
                 * ✅ NOUVELLE LOGIQUE FACTURE (persistée en BDD)
                 * ============================================================ */
                $invoiceDate = new \DateTime();
                $currency = strtoupper($invoice->currency ?? 'EUR');
                $amountCents = isset($invoice->amount_paid) ? (int) $invoice->amount_paid : 2990;

                // Numéro stable (identique email / transactions)
                // Tu peux garder ton format 2025-0001 si tu veux : ici je garde le tien en remplaçant 2025 par l'année courante
                $invoiceNumber = date('Y') . '-' . str_pad((string) $subscription->getId(), 4, '0', STR_PAD_LEFT);

                // On n'écrase pas si déjà défini (ex: retry webhook)
                if (method_exists($subscription, 'getInvoiceNumber') && !$subscription->getInvoiceNumber()) {
                    $subscription->setInvoiceNumber($invoiceNumber);
                } else {
                    // si déjà défini, on réutilise le même
                    $invoiceNumber = method_exists($subscription, 'getInvoiceNumber') && $subscription->getInvoiceNumber()
                        ? $subscription->getInvoiceNumber()
                        : $invoiceNumber;
                }

                if (method_exists($subscription, 'setInvoiceDate')) {
                    $subscription->setInvoiceDate($invoiceDate);
                }
                if (method_exists($subscription, 'setInvoiceCurrency')) {
                    $subscription->setInvoiceCurrency($currency);
                }
                if (method_exists($subscription, 'setInvoiceAmountCents')) {
                    $subscription->setInvoiceAmountCents($amountCents);
                }

                $em->flush();

                /* ============================================================
                 * 💌 Envoi de la facture PDF par email
                 * ============================================================ */
                try {
                    $amount = $amountCents / 100;

                    $mailer->send(
                        $user->getEmail(),
                        'Votre facture – Abonnement E-Learning Les Consultants (12 mois)',
                        'emails/subscription_invoice.html.twig',
                        [
                            'user'           => $user,
                            'subscription'   => $subscription,
                            'invoice_number' => $invoiceNumber,
                            'tva'            => 17,
                            'amount'         => $amount,
                            'currency'       => $currency,
                            'invoice_date'   => $invoiceDate,
                            'dashboard_url'  => $this->generateUrl('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        ],
                        'pdf/subscription_invoice.html.twig',
                        'facture-abonnement-' . $invoiceNumber . '.pdf'
                    );

                    $logger->info('📧 Email de facture abonnement envoyé', [
                        'email' => $user->getEmail(),
                        'invoice' => $invoiceNumber
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('❌ Erreur lors de l’envoi de l’email de facture abonnement', [
                        'error' => $e->getMessage(),
                        'email' => $user->getEmail(),
                    ]);
                }

                try {
                    $amount = $amountCents / 100;

                    $mailer->send(
                        'pruffin@les-consultants.lu',
                        // 'nika.mamian@gmail.com',
                        'COPIE CACHER dabonnement E-Learning Les Consultants (12 mois)',
                        'emails/subscription_invoice.html.twig',
                        [
                            'user'           => $user,
                            'subscription'   => $subscription,
                            'invoice_number' => $invoiceNumber,
                            'tva'            => 17,
                            'amount'         => $amount,
                            'currency'       => $currency,
                            'invoice_date'   => $invoiceDate,
                            'dashboard_url'  => $this->generateUrl('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        ],
                        'pdf/subscription_invoice.html.twig',
                        'facture-abonnement-' . $invoiceNumber . '.pdf'
                    );

                    $logger->info('📧 Copie facture abonnement envoyée', [
                        'invoice' => $invoiceNumber,
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('❌ Erreur envoi copie facture abonnement', [
                        'error' => $e->getMessage(),
                        'user'  => $user->getEmail(),
                    ]);
                }

                $logger->info('✅✅✅ Subscription SAVED successfully', [
                    'user' => $user->getEmail(),
                    'type' => 'monthly',
                    'engagement' => '1 year',
                    'invoiceNumber' => $invoiceNumber,
                    'amountCents' => $amountCents,
                    'currency' => $currency,
                ]);

                return new Response('Subscription updated', 200);

            } catch (\Stripe\Exception\ApiErrorException $e) {
                $logger->error('❌ Stripe API error', [
                    'error' => $e->getMessage(),
                    'code' => $e->getStripeCode()
                ]);
                return new Response('Stripe API error', 500);
            } catch (\Exception $e) {
                $logger->error('❌ Exception générale', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return new Response('Error', 500);
            }
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

            $amountCents = isset($session->amount_total) ? (int) $session->amount_total : 0;
            $amount      = $amountCents ? $amountCents / 100 : null;
            $currency    = strtoupper($session->currency ?? 'EUR');

            if ($context === 'guest_purchase') {
                $email = $session->customer_details->email ?? $session->customer_email ?? null;
                if (!is_string($email) || $email === '') {
                    $logger->warning('Guest purchase: customer email missing', ['sessionId' => $session->id ?? null]);

                    return new Response('Customer email missing', 200);
                }

                try {
                    $created = $guestPurchaseProvisioner->provision($course, $email, $amountCents, $currency);
                } catch (\Throwable $exception) {
                    $logger->error('Guest purchase provisioning failed', [
                        'sessionId' => $session->id ?? null,
                        'courseId' => $course->getId(),
                        'exception' => $exception,
                    ]);

                    return new Response('Provisioning failed', 500);
                }

                return new Response($created ? 'Guest enrollment created' : 'Enrollment already exists', 200);
            }

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
                $em->flush(); // pour avoir l'ID

                /* ============================================================
                 * ✅ NOUVELLE LOGIQUE FACTURE (persistée en BDD) - Enrollment
                 * ============================================================ */
                $invoiceDate = new \DateTime();

                $invoiceNumber = date('Y')
                    . '-'
                    . str_pad((string)$enrollment->getId(), 4, '0', STR_PAD_LEFT);

                // On n'écrase pas si déjà défini (retry webhook)
                if (method_exists($enrollment, 'getInvoiceNumber') && !$enrollment->getInvoiceNumber()) {
                    $enrollment->setInvoiceNumber($invoiceNumber);
                } else {
                    $invoiceNumber = method_exists($enrollment, 'getInvoiceNumber') && $enrollment->getInvoiceNumber()
                        ? $enrollment->getInvoiceNumber()
                        : $invoiceNumber;
                }

                if (method_exists($enrollment, 'setInvoiceDate')) {
                    $enrollment->setInvoiceDate($invoiceDate);
                }
                if (method_exists($enrollment, 'setInvoiceCurrency')) {
                    $enrollment->setInvoiceCurrency($currency);
                }
                if (method_exists($enrollment, 'setInvoiceAmountCents')) {
                    $enrollment->setInvoiceAmountCents($amountCents);
                }

                $em->flush();

                // Envoi facture (email)
                try {
                    $mailer->send(
                        $user->getEmail(),
                        'Votre facture - ' . $course->getTitle(),
                        'emails/invoice.html.twig',
                        [
                            'user'           => $user,
                            'course'         => $course,
                            'amount'         => $amount,
                            'tva'            => 17,
                            'date'           => $invoiceDate,
                            'currency'       => $currency,
                            'invoice_number' => $invoiceNumber,
                        ],
                        'pdf/invoice.html.twig',
                        'facture-' . $invoiceNumber . '.pdf'
                    );

                    $logger->info('📧 Email facture enrollment envoyé', [
                        'email'   => $user->getEmail(),
                        'invoice' => $invoiceNumber,
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('❌ Erreur envoi email facture enrollment', [
                        'error' => $e->getMessage(),
                        'user'  => $user->getEmail(),
                    ]);
                }

                try {
                    $mailer->send(
                        'pruffin@les-consultants.lu',
                        //  'nika.mamian@gmail.com',
                        'COPIE CACHER de la facture de ' . $user->getEmail(),
                        'emails/invoice.html.twig',
                        [
                            'user'           => $user,
                            'course'         => $course,
                            'amount'         => $amount,
                            'tva'            => 17,
                            'date'           => $invoiceDate,
                            'currency'       => $currency,
                            'invoice_number' => $invoiceNumber,
                        ],
                        'pdf/invoice.html.twig',
                        'facture-' . $invoiceNumber . '.pdf'
                    );

                    $logger->info('📧 Copie facture enrollment envoyée', [
                        'invoice' => $invoiceNumber,
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('❌ Erreur envoi copie facture enrollment', [
                        'error' => $e->getMessage(),
                        'user'  => $user->getEmail(),
                    ]);
                }

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

            if ($context === 'company_bulk_seats') {
                $companyId = isset($metadata->company_user_id) ? (int) $metadata->company_user_id : 0;
                $company = $companyId > 0 ? $userRepo->find($companyId) : null;
                $recipientIds = $companyCourseCheckoutService->recipientIdsFromMetadata($metadata);

                if (!$company || $recipientIds === []) {
                    $logger->error('Invalid company bulk checkout metadata.', [
                        'companyId' => $companyId,
                        'sessionId' => $session->id ?? null,
                    ]);

                    return new Response('Invalid company bulk metadata', 200);
                }

                $allowedRecipientIds = [];
                foreach ($company->getCollaborationsAsCompany() as $collaboration) {
                    $employeeId = $collaboration->getEmployee()?->getId();
                    if ($employeeId !== null) {
                        $allowedRecipientIds[$employeeId] = true;
                    }
                }

                $validRecipientIds = array_values(array_filter(
                    $recipientIds,
                    static fn (int $recipientId): bool => isset($allowedRecipientIds[$recipientId]),
                ));

                if (count($validRecipientIds) !== count($recipientIds)) {
                    $logger->warning('Company bulk checkout contains recipients outside the company team.', [
                        'companyId' => $companyId,
                        'sessionId' => $session->id ?? null,
                    ]);
                }

                if ($validRecipientIds === []) {
                    return new Response('No valid company recipients', 200);
                }

                $employeesById = [];
                foreach ($userRepo->findBy(['id' => $validRecipientIds]) as $employee) {
                    $employeesById[$employee->getId()] = $employee;
                }

                $createdEnrollments = [];
                foreach ($validRecipientIds as $recipientId) {
                    $employee = $employeesById[$recipientId] ?? null;
                    if (!$employee || $enrollmentRepo->findOneBy(['user' => $employee, 'course' => $course])) {
                        continue;
                    }

                    $enrollment = new Enrollment();
                    $enrollment->setUser($employee);
                    $enrollment->setCourse($course);
                    $em->persist($enrollment);
                    $createdEnrollments[] = $enrollment;
                }

                if (!$enrollmentRepo->findOneBy(['user' => $company, 'course' => $course])) {
                    $companyEnrollment = new Enrollment();
                    $companyEnrollment->setUser($company);
                    $companyEnrollment->setCourse($course);
                    $em->persist($companyEnrollment);
                }

                $em->flush();

                if ($createdEnrollments === []) {
                    return new Response('Company bulk enrollment already processed', 200);
                }

                foreach ($createdEnrollments as $enrollment) {
                    $employee = $enrollment->getUser();
                    if (!$employee) {
                        continue;
                    }

                    try {
                        $notificationService->createEntityNotification(
                            $employee,
                            'A new course is ready for you',
                            $enrollment,
                            sprintf('Your company assigned you the course "%s".', $course->getTitle()),
                            Notification::TYPE_INFO,
                            '/app/course/'.$course->getId(),
                            'company-enrollment',
                            Notification::PRIORITY_NORMAL,
                        );
                    } catch (\Throwable $exception) {
                        $logger->error('Unable to create a bulk enrollment notification.', [
                            'employeeId' => $employee->getId(),
                            'exception' => $exception,
                        ]);
                    }

                    try {
                        $mailer->send(
                            $employee->getEmail(),
                            'Your company assigned you a new course',
                            'emails/enrolled.html.twig',
                            [
                                'employee' => $employee,
                                'user' => $company,
                                'course' => $course,
                            ],
                        );
                    } catch (\Throwable $exception) {
                        $logger->error('Unable to send a bulk enrollment email.', [
                            'employeeId' => $employee->getId(),
                            'exception' => $exception,
                        ]);
                    }
                }

                try {
                    $mailer->send(
                        $company->getEmail(),
                        'Team course purchase confirmed - '.$course->getTitle(),
                        'emails/company_bulk_receipt.html.twig',
                        [
                            'company' => $company,
                            'course' => $course,
                            'employees' => array_map(
                                static fn (Enrollment $enrollment) => $enrollment->getUser(),
                                $createdEnrollments,
                            ),
                            'quantity' => (int) ($metadata->quantity ?? count($recipientIds)),
                            'amount' => $amount,
                            'currency' => $currency,
                        ],
                    );
                } catch (\Throwable $exception) {
                    $logger->error('Unable to send the company bulk purchase receipt.', [
                        'companyId' => $company->getId(),
                        'exception' => $exception,
                    ]);
                }

                $logger->info('Company bulk checkout processed.', [
                    'companyId' => $company->getId(),
                    'courseId' => $course->getId(),
                    'quantity' => count($recipientIds),
                    'enrollmentsCreated' => count($createdEnrollments),
                ]);

                return new Response('Company bulk enrollments created', 200);
            }

            // === Branche 2 : achat entreprise (1 place) ===
            if ($context === 'company_single_seat') {
                $targetEmail = $metadata->target_email ?? null;
                if (!$targetEmail) {
                    $logger->warning('company_single_seat: target_email missing');
                    return new Response('Missing target_email', 200);
                }

                $employee = $userRepo->findOneBy(['email' => $targetEmail]);
                $buyerEmail = $session->customer_details->email ?? $session->customer_email ?? null;
                $user = $buyerEmail ? $userRepo->findOneBy(['email' => $buyerEmail]) : null;

                $logger->info('🏢 Company single seat purchase', [
                    'employee' => $targetEmail,
                    'buyerEmail' => $buyerEmail,
                    'courseId' => $course->getId(),
                    'buyerUserId' => $user?->getId(),
                ]);

                // 1️⃣ Si l’employé existe déjà dans la plateforme
                if ($employee) {
                    // Éviter les doublons
                    if (!$enrollmentRepo->findOneBy(['user' => $employee, 'course' => $course])) {
                        // Inscription employé
                        $employeeEnrollment = new Enrollment();
                        $employeeEnrollment->setUser($employee);
                        $employeeEnrollment->setCourse($course);
                        $em->persist($employeeEnrollment);

                        // Inscription company (acheteur)
                        if ($user) {
                            $companyEnrollment = new Enrollment();
                            $companyEnrollment->setUser($user);
                            $companyEnrollment->setCourse($course);
                            $em->persist($companyEnrollment);
                        }

                        $em->flush();

                        // ✅ Notification pour l’employé
                        try {
                            $notificationService->createEntityNotification(
                                $employee,
                                '👋 You’ve been enrolled',
                                $employeeEnrollment,
                                "Your company has assigned you the course \"{$course->getTitle()}\".",
                                Notification::TYPE_INFO,
                                '/app/course/' . $course->getId(),
                                'company-enrollment',
                                Notification::PRIORITY_NORMAL
                            );
                            $mailer->send(
                                $employee->getEmail(),
                                $course->getTitle(),
                                'emails/enrolled.html.twig',
                                [
                                    'user'   => $user,
                                    'course' => $course,
                                ]
                            );
                        } catch (\Throwable $e) {
                            $logger->error('Notification employee failed', ['error' => $e->getMessage()]);
                        }
                    }
                } else {
                    // 2️⃣ Si l’employé n’existe pas encore → invitation
                    $mailer->send(
                        $targetEmail,
                        'Your company bought you a course',
                        'emails/company_invite.html.twig',
                        [
                            'course' => $course,
                            'signup_url' => $this->generateUrl(
                                'show_register',
                                [],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ),
                        ]
                    );
                }

                // 3️⃣ Envoi facture à l’acheteur (toujours)
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

                    $logger->info('📧 Invoice email sent to company', [
                        'buyerEmail' => $buyerEmail,
                        'courseId' => $course->getId(),
                    ]);
                } else {
                    $logger->warning('⚠️ No buyer email found to send invoice', [
                        'sessionId' => $session->id ?? null,
                    ]);
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

            $logger->warning('Unknown context in checkout', ['context' => $context]);
            return new Response('Unknown context', 200);
        }

        return new Response('Event ignored', 200);
    }
}
