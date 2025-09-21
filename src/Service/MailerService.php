<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendEmail(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $from = null
    ): void {
        $email = (new Email())
            ->from('no-reply@test-51ndgwv6wqqlzqx8.mlsender.net')
            ->to($to)
            ->subject($subject)
            ->html($htmlContent);

        $this->mailer->send($email);
    }
}
