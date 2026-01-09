<?php

namespace App\Controller;

use App\Repository\EnrollmentRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class InvoiceController extends AbstractController
{
    #[Route('/app/invoices/enrollment/{id}', name: 'app_invoice_enrollment_download', methods: ['GET'])]
    public function downloadEnrollmentInvoice(
        int $id,
        EnrollmentRepository $enrollmentRepo,
        EntityManagerInterface $em,
        Environment $twig
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $enrollment = $enrollmentRepo->find($id);
        if (!$enrollment || $enrollment->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        // ✅ AUTO-HEAL LEGACY: si pas d'infos facture, on régénère et on stocke
        if (!$enrollment->getInvoiceNumber()) {
            $invoiceDate = $enrollment->getCreatedAt() ?? new \DateTime();
            $currency = 'EUR';

            // Montant fallback: depuis CoursePrice EUR (si dispo)
            $amountCents = null;
            $course = $enrollment->getCourse();
            if ($course && method_exists($course, 'getCoursePrices')) {
                foreach ($course->getCoursePrices() as $price) {
                    if (method_exists($price, 'getCurrency') && strtoupper($price->getCurrency()) === 'EUR') {
                        $amountCents = (int) $price->getPrice(); // normalement déjà en cents
                        break;
                    }
                }
            }
            if ($amountCents === null) {
                $amountCents = 0; // fallback ultime
            }

            // Numéro stable (sans random) => garantit téléchargement cohérent
            $invoiceNumber = date('Y', $invoiceDate->getTimestamp())
                . '-'
                . str_pad((string) $enrollment->getId(), 4, '0', STR_PAD_LEFT);

            $enrollment->setInvoiceNumber($invoiceNumber);
            $enrollment->setInvoiceDate($invoiceDate);
            $enrollment->setInvoiceCurrency($currency);
            $enrollment->setInvoiceAmountCents($amountCents);

            $em->flush();
        }

        $invoiceNumber = $enrollment->getInvoiceNumber();
        $invoiceDate   = $enrollment->getInvoiceDate() ?? $enrollment->getCreatedAt() ?? new \DateTime();
        $currency      = $enrollment->getInvoiceCurrency() ?? 'EUR';
        $amount        = (($enrollment->getInvoiceAmountCents() ?? 0) / 100);

        $html = $twig->render('pdf/invoice.html.twig', [
            'user'           => $user,
            'course'         => $enrollment->getCourse(),
            'amount'         => $amount,
            'tva'            => 17,
            'date'           => $invoiceDate,
            'currency'       => $currency,
            'invoice_number' => $invoiceNumber,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="facture-' . $invoiceNumber . '.pdf"',
            ]
        );
    }

    #[Route('/app/invoices/subscription', name: 'app_invoice_subscription_download', methods: ['GET'])]
    public function downloadSubscriptionInvoice(
        SubscriptionRepository $subscriptionRepo,
        EntityManagerInterface $em,
        Environment $twig
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $subscription = $subscriptionRepo->findOneBy(['user' => $user]);
        if (!$subscription || !$subscription->isActive()) {
            throw $this->createNotFoundException('Aucune facture abonnement disponible.');
        }

        // ✅ AUTO-HEAL LEGACY: si pas d'infos facture, on régénère et on stocke
        if (!$subscription->getInvoiceNumber()) {
            $invoiceDate = $subscription->getStartDate() ? \DateTime::createFromInterface($subscription->getStartDate()) : new \DateTime();
            $currency = 'EUR';

            // Montant fallback (comme ton plan)
            $amountCents = 2990;

            $invoiceNumber = date('Y', $invoiceDate->getTimestamp())
                . '-'
                . str_pad((string) $subscription->getId(), 4, '0', STR_PAD_LEFT);

            $subscription->setInvoiceNumber($invoiceNumber);
            $subscription->setInvoiceDate($invoiceDate);
            $subscription->setInvoiceCurrency($currency);
            $subscription->setInvoiceAmountCents($amountCents);

            $em->flush();
        }

        $invoiceNumber = $subscription->getInvoiceNumber();
        $invoiceDate   = $subscription->getInvoiceDate()
            ?? ($subscription->getStartDate() ? \DateTime::createFromInterface($subscription->getStartDate()) : new \DateTime());
        $currency      = $subscription->getInvoiceCurrency() ?? 'EUR';
        $amount        = (($subscription->getInvoiceAmountCents() ?? 0) / 100);

        $html = $twig->render('pdf/subscription_invoice.html.twig', [
            'user'           => $user,
            'subscription'   => $subscription,
            'invoice_number' => $invoiceNumber,
            'tva'            => 17,
            'amount'         => $amount,
            'currency'       => $currency,
            'invoice_date'   => $invoiceDate,
            'dashboard_url'  => $this->generateUrl('app_dashboard', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="facture-abonnement-' . $invoiceNumber . '.pdf"',
            ]
        );
    }
}
