<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Dompdf\Dompdf;
use Dompdf\Options;

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
     * Envoie un e-mail avec éventuellement une facture PDF en pièce jointe.
     */
    public function send(
        string $to,
        string $subject,
        string $template,
        array $context = [],
        ?string $pdfTemplate = null,
        ?string $pdfFilename = null
    ): void {
        // Rendu du corps de l’e-mail (HTML)
        $html = $this->twig->render($template, $context);
        $text = strip_tags($html);

        $email = (new Email())
            ->from('contact@les-consultants.com')
            ->to($to)
            ->subject($subject)
            ->text($text)
            ->html($html);

        // Génération et ajout du PDF si demandé
        if ($pdfTemplate !== null && $pdfFilename !== null) {
            $pdfContent = $this->generatePdfFromTemplate($pdfTemplate, $context);
            $email->attach($pdfContent, $pdfFilename, 'application/pdf');
        }

        $this->mailer->send($email);
    }

    /**
     * Envoie un message libre à plusieurs destinataires sans exposer leur adresse entre eux.
     */
    public function sendAdminBroadcast(
        array $bcc,
        string $subject,
        string $message,
        array $cc = []
    ): void {
        $html = $this->twig->render('emails/admin_bulk_message.html.twig', [
            'message' => $message,
        ]);
        $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        $email = (new Email())
            ->from('contact@les-consultants.com')
            ->to('contact@les-consultants.com')
            ->bcc(...$bcc)
            ->subject($subject)
            ->text($text)
            ->html($html);

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        $this->mailer->send($email);
    }

    /**
     * Génère un PDF à partir d’un template Twig (Dompdf)
     */
    private function generatePdfFromTemplate(string $template, array $context): string
    {
        $html = $this->twig->render($template, $context);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true); // utile si tu as des images ou CSS externes

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output(); // Retourne le contenu du PDF en mémoire
    }
}
