<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class GuestPurchaseProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly PasswordSetupInvitationSender $invitationSender,
        private readonly MailerService $mailer,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function provision(Course $course, string $email, int $amountCents, string $currency): bool
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Stripe did not provide a valid customer email.');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNewUser = false;

        if (!$user) {
            $user = (new User())
                ->setEmail($email)
                ->setUsername($this->usernameFromEmail($email))
                ->setRoles(['ROLE_EMPLOYEE']);
            $this->entityManager->persist($user);
            $isNewUser = true;
        }

        if ($this->enrollmentRepository->findOneBy(['user' => $user, 'course' => $course])) {
            if ($user->getPassword() === null) {
                $this->sendActivationInvitation($user, $course);
            }

            return false;
        }

        $enrollment = (new Enrollment())
            ->setUser($user)
            ->setCourse($course);
        $this->entityManager->persist($enrollment);
        $this->entityManager->flush();

        $invoiceDate = new \DateTime();
        $invoiceNumber = date('Y').'-'.str_pad((string) $enrollment->getId(), 4, '0', STR_PAD_LEFT);
        $enrollment
            ->setInvoiceNumber($invoiceNumber)
            ->setInvoiceDate($invoiceDate)
            ->setInvoiceCurrency($currency)
            ->setInvoiceAmountCents($amountCents);
        $this->entityManager->flush();

        if ($isNewUser || $user->getPassword() === null) {
            $this->sendActivationInvitation($user, $course);
        }

        $this->sendInvoice($user, $course, $amountCents, $currency, $invoiceDate, $invoiceNumber);

        try {
            $this->notificationService->createEntityNotification(
                $user,
                'Payment confirmed',
                $enrollment,
                sprintf('Your enrollment in “%s” is ready.', $course->getTitle()),
                Notification::TYPE_SUCCESS,
                '/app/course/'.$course->getId(),
                'fa-solid fa-circle-check',
                Notification::PRIORITY_HIGH
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to create guest purchase notification.', ['exception' => $exception]);
        }

        return true;
    }

    private function sendInvoice(User $user, Course $course, int $amountCents, string $currency, \DateTime $date, string $invoiceNumber): void
    {
        try {
            $this->mailer->send(
                $user->getEmail(),
                'Your invoice - '.$course->getTitle(),
                'emails/invoice.html.twig',
                [
                    'user' => $user,
                    'course' => $course,
                    'amount' => $amountCents / 100,
                    'tva' => 17,
                    'date' => $date,
                    'currency' => $currency,
                    'invoice_number' => $invoiceNumber,
                ],
                'pdf/invoice.html.twig',
                'invoice-'.$invoiceNumber.'.pdf'
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to send guest purchase invoice.', [
                'email' => $user->getEmail(),
                'exception' => $exception,
            ]);
        }
    }

    private function sendActivationInvitation(User $user, Course $course): void
    {
        $this->invitationSender->sendToUsers(
            [$user],
            'Activate your Les Consultants learning account',
            sprintf('Your payment for “%s” is confirmed. Set your password to access your course and learner account.', $course->getTitle())
        );
    }

    private function usernameFromEmail(string $email): string
    {
        $localPart = explode('@', $email, 2)[0];
        $username = trim((string) preg_replace('/[^\pL\pN]+/u', ' ', $localPart));

        return mb_substr($username !== '' ? ucwords($username) : 'New learner', 0, 180);
    }
}
