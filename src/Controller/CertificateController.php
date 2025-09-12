<?php

namespace App\Controller;

use FPDF;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CertificateController extends AbstractController
{
    #[Route('app/certificate/{name}', name: 'generate_certificate')]
    public function generateCertificate(string $name): Response
    {
        $pdf = new \FPDF('L', 'mm', 'A4'); // L = paysage, A4
        $pdf->AddPage();

        // Dimensions page
        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();

        // Ajout du modèle en fond
        $background = $this->getParameter('kernel.project_dir') . '/public/build/images/certificate-template.png';
        $pdf->Image($background, 0, 0, $pageWidth, $pageHeight);

        // Générer un numéro de certificat unique
        $certificateNumber = 'CERT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        // --- Nom ---
        $pdf->SetFont('Arial', 'B', 26);
        $pdf->SetTextColor(0, 0, 0);
        $nameWidth = $pdf->GetStringWidth(utf8_decode($name));
        $x = ($pageWidth - $nameWidth) / 2;
        $y = 95; // Ajuster selon ton modèle
        $pdf->SetXY($x, $y);
        $pdf->Cell($nameWidth, 10, utf8_decode($name));

        // --- Date ---
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(60, 160); // Ajuste selon la zone "DATE"
        $pdf->Cell(40, 10, (new \DateTime())->format('d/m/Y'));

        // --- Directeur ---
        $pdf->SetXY(210, 160); // Ajuste selon la zone "DIRECTOR"
        $pdf->Cell(40, 10, 'Director');

        // --- Numéro de certificat ---
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(10, $pageHeight - 15);
        $pdf->Cell(0, 10, 'Certificate No: ' . $certificateNumber);

        // Générer le PDF en mode téléchargement
        $pdfContent = $pdf->Output('S');

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="certificate_' . $certificateNumber . '.pdf"',
            ]
        );
    }
}
