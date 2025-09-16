<?php

namespace App\Controller;

use FPDF;
use App\Entity\Certificate;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CertificateRepository;
use App\Repository\QuizAttemptRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CertificateController extends AbstractController
{
#[Route('/app/certificates', name: 'app_certificates')]
public function certificate(QuizAttemptRepository $quizAttemptRepo): Response
{
    $user = $this->getUser();

    // on va chercher *tous* les quiz attempts de cet utilisateur
     $userAttempts = $quizAttemptRepo->findByUser($user);

    if (!$userAttempts) {
        $this->addFlash('info', 'No quiz Found for this User.');
        return $this->redirectToRoute('app_dashboard');
    }

    return $this->render('certificates/index.html.twig', [
        'userAttempts' => $userAttempts
    ]);
}

#[Route('app/certificate/{name}', name: 'generate_certificate')]
public function generateCertificate(
    string $name,
    QuizAttemptRepository $quizAttemptRepo,
    EntityManagerInterface $em,
    CertificateRepository $certificateRepo
): Response {
    $user = $this->getUser();

    $userAttempt = $quizAttemptRepo->findOneByUserId($user->getId());
    if ($userAttempt && $userAttempt->isPassed() === true) {

        $course = $userAttempt->getCourse();

        // Vérifie si un certificat existe déjà pour ce user + course
        $certificate = $certificateRepo->findOneBy([
            'passed' => $user,
            'course' => $course,
        ]);

        if (!$certificate) {
            // Si pas encore de certificat → on en crée un
            $certificateNumber = 'CERT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

            $certificate = new Certificate();
            $certificate->setTitle("Certificate of completion: " . $course->getTitle());
            $certificate->setRef($certificateNumber);
            $certificate->setCourse($course);
            $certificate->setPassed($user);

            $em->persist($certificate);
            $em->flush();
        } else {
            // Si déjà existant → on réutilise son numéro
            $certificateNumber = $certificate->getRef();
        }

        // -------- Génération du PDF --------
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->AddPage();

        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();

        $background = $this->getParameter('kernel.project_dir') . '/public/build/images/certificate-template.png';
        $pdf->Image($background, 0, 0, $pageWidth, $pageHeight);

        // Nom de l’étudiant
        $pdf->SetFont('Arial', 'B', 26);
        $pdf->SetTextColor(0, 0, 0);
        $nameWidth = $pdf->GetStringWidth(utf8_decode($name));
        $x = ($pageWidth - $nameWidth) / 2;
        $y = 95;
        $pdf->SetXY($x, $y);
        $pdf->Cell($nameWidth, 10, utf8_decode($name));

        // Titre du cours
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Ln(12);
        $pdf->Cell($pageWidth, 10, utf8_decode($course->getTitle()), 0, 0, 'C');

        // Date
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(100, 178);
        $pdf->Cell(40, 10, (new \DateTime())->format('d/m/Y'), 0, 0, 'L');

        // Ref du certificat
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($pageWidth - 70, 10);
        $pdf->Cell(60, 10, 'Ref: ' . $certificateNumber, 0, 0, 'R');

        $pdfContent = $pdf->Output('S');

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="certificate_' . $certificateNumber . '.pdf"',
            ]
        );
    } else {
        $this->addFlash('info', 'No quiz Found for this User.');
        return $this->redirectToRoute('app_dashboard');
    }
}


}
