<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerService
{
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(MailerInterface $mailer, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    /**
     * @param string      $to       Destinataire
     * @param string      $subject  Sujet du mail
     * @param string      $template Nom du template Twig (ex: 'emails/welcome.html.twig')
     * @param array       $context  Variables passées au template Twig
     */
    public function send(
        string $to,
        string $subject,
        string $template,
        array $context = [],
    ): void {
        // rendu du template HTML
        $html = $this->twig->render($template, $context);

        // tu peux aussi prévoir un "fallback" texte brut
        $text = strip_tags($html);

        $email = (new Email())
            // ->from('contact@les-consultants.com')
            ->from('pro@ultrapop.com')
            ->to($to)
            ->subject($subject)
            ->text($text)
            ->html($html);

        $this->mailer->send($email);
    }
}
