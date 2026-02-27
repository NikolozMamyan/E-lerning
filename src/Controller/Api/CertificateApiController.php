<?php

namespace App\Controller\Api;

use App\Entity\Certificate;
use App\Repository\CertificateRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class CertificateApiController extends AbstractController
{
    #[Route('/certificates', name: 'certificates_list', methods: ['GET'])]
    public function list(QuizAttemptRepository $quizAttemptRepo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $attempts = $quizAttemptRepo->findByUser($user);

        // ⚠️ On construit un DTO simple pour éviter les problèmes de sérialisation (relations, circular refs, etc.)
        $data = array_map(function ($attempt) {
            $course = $attempt->getCourse();

            return [
                'attemptId' => $attempt->getId(),
                'passed' => (bool) $attempt->isPassed(),
                'createdAt' => $attempt->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'course' => [
                    'id' => $course?->getId(),
                    'title' => $course?->getTitle(),
                ],
            ];
        }, $attempts);

        return $this->json($data);
    }

    #[Route('/certificates/{id}/pdf', name: 'certificate_pdf', methods: ['GET'])]
    public function pdf(
        int $id,
        QuizAttemptRepository $quizAttemptRepo,
        EntityManagerInterface $em,
        CertificateRepository $certificateRepo
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $attempt = $quizAttemptRepo->find($id);

        if (!$attempt || $attempt->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Attempt not found'], 404);
        }

        if (!$attempt->isPassed()) {
            return $this->json(['message' => 'Attempt not passed'], 403);
        }

        $course = $attempt->getCourse();

        $certificate = $certificateRepo->findOneBy([
            'passed' => $user,
            'course' => $course,
        ]);

        if (!$certificate) {
            $certificateNumber = 'CERT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

            $certificate = new Certificate();
            $certificate->setTitle("Certificate of completion: " . $course->getTitle());
            $certificate->setRef($certificateNumber);
            $certificate->setCourse($course);
            $certificate->setPassed($user);

            $em->persist($certificate);
            $em->flush();
        } else {
            $certificateNumber = $certificate->getRef();
        }

        // -------- Génération du PDF --------
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->AddPage();

        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();

        $background = $this->getParameter('kernel.project_dir') . '/public/build/images/certificate-template.png';
        $pdf->Image($background, 0, 0, $pageWidth, $pageHeight);

        // Nom
        $pdf->SetFont('Arial', 'B', 26);
        $pdf->SetTextColor(0, 0, 0);
        $name = utf8_decode($user->getUserName());
        $nameWidth = $pdf->GetStringWidth($name);
        $x = ($pageWidth - $nameWidth) / 2;
        $y = 95;
        $pdf->SetXY($x, $y);
        $pdf->Cell($nameWidth, 10, $name);

        // Titre cours avec wrap
        $pdf->SetFont('Arial', 'B', 18);
        $title = utf8_decode($course->getTitle());
        $maxWidth = $pageWidth * 0.8;
        $titleY = 130;
        $pdf->SetY($titleY);

        $titleWidth = $pdf->GetStringWidth($title);
        if ($titleWidth > $maxWidth) {
            $x = ($pageWidth - $maxWidth) / 2;
            $pdf->SetX($x);
            $pdf->MultiCell($maxWidth, 10, $title, 0, 'C');
        } else {
            $pdf->SetXY(0, $titleY);
            $pdf->Cell($pageWidth, 10, $title, 0, 0, 'C');
        }

        // Durée cours
        $totalDurationSeconds = 0;
        foreach ($course->getVideos() as $video) {
            $totalDurationSeconds += $video->getDuration();
        }
        $totalDurationMinutes = (int) round($totalDurationSeconds / 60);

        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(0, 150);
        $pdf->Cell($pageWidth, 10, utf8_decode("Course Duration : " . $totalDurationMinutes . " min"), 0, 0, 'C');

        // Date
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(100, 178);
        $pdf->Cell(40, 10, $attempt->getCreatedAt()->format('d/m/Y'), 0, 0, 'L');

        // Ref
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($pageWidth - 70, 10);
        $pdf->Cell(60, 10, 'Ref: ' . $certificateNumber, 0, 0, 'R');

        $pdfContent = $pdf->Output('S');

        // Pour Flutter: mieux vaut "inline" + filename, ou "attachment" si tu veux forcer le download côté navigateur.
        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="certificate_' . $certificateNumber . '.pdf"',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}