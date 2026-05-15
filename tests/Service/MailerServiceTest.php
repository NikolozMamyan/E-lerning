<?php

namespace App\Tests\Service;

use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class MailerServiceTest extends TestCase
{
    public function testSendAdminBroadcastBuildsExpectedMessage(): void
    {
        $capturedEmail = null;

        $mailer = new class($capturedEmail) implements MailerInterface {
            public ?Email $capturedEmail = null;

            public function __construct(?Email &$capturedEmail)
            {
                $this->capturedEmail = &$capturedEmail;
            }

            public function send(\Symfony\Component\Mime\RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                if ($message instanceof Email) {
                    $this->capturedEmail = $message;
                }
            }
        };

        $twig = new Environment(new ArrayLoader([
            'emails/admin_bulk_message.html.twig' => '<p>{{ message|nl2br }}</p>',
        ]));

        $service = new MailerService($mailer, $twig);
        $service->sendAdminBroadcast(
            ['user1@example.com', 'user2@example.com'],
            'Sujet test',
            "Bonjour\nMessage admin",
            ['copy@example.com']
        );

        self::assertInstanceOf(Email::class, $capturedEmail);
        self::assertSame('Sujet test', $capturedEmail->getSubject());
        self::assertSame(['contact@les-consultants.com'], array_map(
            static fn ($address) => $address->getAddress(),
            $capturedEmail->getTo()
        ));
        self::assertSame(['user1@example.com', 'user2@example.com'], array_map(
            static fn ($address) => $address->getAddress(),
            $capturedEmail->getBcc()
        ));
        self::assertSame(['copy@example.com'], array_map(
            static fn ($address) => $address->getAddress(),
            $capturedEmail->getCc()
        ));
        self::assertStringContainsString('Bonjour', $capturedEmail->getHtmlBody() ?? '');
    }
}
