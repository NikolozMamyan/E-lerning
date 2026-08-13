<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use Psr\Log\LoggerInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class PasswordSetupInvitationSender
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private readonly MailerService $mailerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param iterable<User> $users
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendToUsers(iterable $users, string $subject, string $message): array
    {
        $result = [
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($users as $user) {
            if (!$user->getEmail()) {
                ++$result['skipped'];
                continue;
            }

            try {
                $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            } catch (TooManyPasswordRequestsException $exception) {
                ++$result['skipped'];
                $this->logger->info('Password setup invitation skipped because of reset throttling.', [
                    'userId' => $user->getId(),
                    'availableAt' => $exception->getAvailableAt()->format(DATE_ATOM),
                ]);
                continue;
            } catch (\Throwable $exception) {
                ++$result['failed'];
                $this->logger->error('Unable to generate a password setup link.', [
                    'userId' => $user->getId(),
                    'exception' => $exception,
                ]);
                continue;
            }

            try {
                $this->mailerService->send(
                    $user->getEmail(),
                    $subject,
                    'emails/password_setup_invitation.html.twig',
                    [
                        'user' => $user,
                        'subject' => $subject,
                        'message' => $message,
                        'resetToken' => $resetToken,
                    ]
                );
                ++$result['sent'];
            } catch (\Throwable $exception) {
                ++$result['failed'];

                // Do not leave an unusable token throttling a later retry.
                try {
                    $this->resetPasswordRequestRepository->removeRequests($user);
                } catch (\Throwable $cleanupException) {
                    $this->logger->error('Unable to clean up an unsent password setup token.', [
                        'userId' => $user->getId(),
                        'exception' => $cleanupException,
                    ]);
                }

                $this->logger->error('Unable to send a password setup invitation.', [
                    'userId' => $user->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        return $result;
    }
}
