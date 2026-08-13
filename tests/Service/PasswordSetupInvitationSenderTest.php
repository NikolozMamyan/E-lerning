<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use App\Service\MailerService;
use App\Service\PasswordSetupInvitationSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class PasswordSetupInvitationSenderTest extends TestCase
{
    public function testItGeneratesAndSendsOnePersonalTokenPerUser(): void
    {
        $firstUser = $this->createUser('first@example.com', 'First');
        $secondUser = $this->createUser('second@example.com', 'Second');
        $generatedTokens = [];

        $resetPasswordHelper = $this->createMock(ResetPasswordHelperInterface::class);
        $resetPasswordHelper
            ->expects(self::exactly(2))
            ->method('generateResetToken')
            ->willReturnCallback(static function (User $user) use (&$generatedTokens): ResetPasswordToken {
                $tokenValue = str_repeat($user->getEmail() === 'first@example.com' ? 'a' : 'b', 40);
                $token = new ResetPasswordToken(
                    $tokenValue,
                    new \DateTimeImmutable('+1 day'),
                    time()
                );
                $generatedTokens[$user->getEmail()] = $token;

                return $token;
            });

        $mailerService = $this->createMock(MailerService::class);
        $mailerService
            ->expects(self::exactly(2))
            ->method('send')
            ->withConsecutive(
                [
                    'first@example.com',
                    'Welcome',
                    'emails/password_setup_invitation.html.twig',
                    self::callback(static fn (array $context): bool => $context['user'] === $firstUser
                        && $context['resetToken']->getToken() === str_repeat('a', 40)
                        && $context['message'] === 'Choose your password.'),
                ],
                [
                    'second@example.com',
                    'Welcome',
                    'emails/password_setup_invitation.html.twig',
                    self::callback(static fn (array $context): bool => $context['user'] === $secondUser
                        && $context['resetToken']->getToken() === str_repeat('b', 40)
                        && $context['message'] === 'Choose your password.'),
                ]
            );

        $repository = $this->createMock(ResetPasswordRequestRepository::class);
        $repository->expects(self::never())->method('removeRequests');

        $sender = new PasswordSetupInvitationSender(
            $resetPasswordHelper,
            $repository,
            $mailerService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertSame(
            ['sent' => 2, 'skipped' => 0, 'failed' => 0],
            $sender->sendToUsers([$firstUser, $secondUser], 'Welcome', 'Choose your password.')
        );
        self::assertNotSame(
            $generatedTokens['first@example.com']->getToken(),
            $generatedTokens['second@example.com']->getToken()
        );
    }

    public function testItCleansUpTokenWhenEmailDeliveryFails(): void
    {
        $user = $this->createUser('user@example.com', 'User');
        $resetPasswordHelper = $this->createMock(ResetPasswordHelperInterface::class);
        $resetPasswordHelper
            ->method('generateResetToken')
            ->willReturn(new ResetPasswordToken(str_repeat('c', 40), new \DateTimeImmutable('+1 day'), time()));

        $mailerService = $this->createMock(MailerService::class);
        $mailerService
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP unavailable'));

        $repository = $this->createMock(ResetPasswordRequestRepository::class);
        $repository->expects(self::once())->method('removeRequests')->with($user);

        $sender = new PasswordSetupInvitationSender(
            $resetPasswordHelper,
            $repository,
            $mailerService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertSame(
            ['sent' => 0, 'skipped' => 0, 'failed' => 1],
            $sender->sendToUsers([$user], 'Welcome', 'Choose your password.')
        );
    }

    private function createUser(string $email, string $username): User
    {
        return (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setRoles(['ROLE_EMPLOYEE']);
    }
}
