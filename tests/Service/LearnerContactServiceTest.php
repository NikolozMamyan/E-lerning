<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LearnerContactService;
use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class LearnerContactServiceTest extends TestCase
{
    public function testInvalidContactCannotSendEmail(): void
    {
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::never())->method('send');
        $service = new LearnerContactService($mailer, Validation::createValidator());
        $this->expectException(\InvalidArgumentException::class);
        $service->send(['firstname' => 'Member', 'lastname' => 'Test', 'email' => 'invalid', 'message' => 'Help']);
    }

    public function testValidContactUsesExistingEmailTemplate(): void
    {
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::once())->method('send')->with(
            'contact@elearning-lesconsultants.com', self::anything(), 'emails/contact.html.twig',
            ['firstname' => 'Member', 'lastname' => 'Test', 'email' => 'member@example.com', 'phone' => '', 'message' => 'Help']
        );
        (new LearnerContactService($mailer, Validation::createValidator()))->send(['firstname' => ' Member ', 'lastname' => 'Test', 'email' => 'member@example.com', 'message' => 'Help']);
    }
}
