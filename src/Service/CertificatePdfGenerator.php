<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Certificate;
use App\Entity\QuizAttempt;
use App\Repository\CertificateRepository;
use Doctrine\ORM\EntityManagerInterface;
use FPDF;
use Symfony\Component\HttpKernel\KernelInterface;

final class CertificatePdfGenerator
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function generate(QuizAttempt $attempt): array
    {
        if (!$attempt->isPassed()) {
            throw new \DomainException('A certificate can only be generated for a passed attempt.');
        }

        $user = $attempt->getUser();
        $course = $attempt->getCourse();
        if ($user === null || $course === null) {
            throw new \LogicException('The quiz attempt must be linked to a user and a course.');
        }

        $certificate = $this->certificateRepository->findOneBy([
            'passed' => $user,
            'course' => $course,
        ]);

        if (!$certificate instanceof Certificate) {
            $certificate = (new Certificate())
                ->setTitle('Certificate of completion: '.$course->getTitle())
                ->setRef('CERT-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))))
                ->setCourse($course)
                ->setPassed($user);

            $this->entityManager->persist($certificate);
            $this->entityManager->flush();
        }

        $certificateNumber = (string) $certificate->getRef();
        $safeCertificateNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', $certificateNumber) ?: 'certificate';
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();

        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();
        $background = $this->kernel->getProjectDir().'/public/build/images/certificate-template.png';
        if (!is_file($background)) {
            throw new \RuntimeException('The certificate template image is missing.');
        }

        $pdf->Image($background, 0, 0, $pageWidth, $pageHeight);

        $name = $this->encode($user->getUsername());
        $pdf->SetFont('Arial', 'B', 26);
        $pdf->SetTextColor(0, 0, 0);
        $nameWidth = $pdf->GetStringWidth($name);
        $pdf->SetXY(($pageWidth - $nameWidth) / 2, 95);
        $pdf->Cell($nameWidth, 10, $name);

        $pdf->SetFont('Arial', 'B', 18);
        $title = $this->encode((string) $course->getTitle());
        $maxWidth = $pageWidth * 0.8;
        $titleY = 130;

        if ($pdf->GetStringWidth($title) > $maxWidth) {
            $pdf->SetY($titleY);
            $pdf->SetX(($pageWidth - $maxWidth) / 2);
            $pdf->MultiCell($maxWidth, 10, $title, 0, 'C');
        } else {
            $pdf->SetXY(0, $titleY);
            $pdf->Cell($pageWidth, 10, $title, 0, 0, 'C');
        }

        $durationLabel = $certificate->getDurationLabel();
        if (!$durationLabel) {
            $totalDurationSeconds = 0;
            foreach ($course->getVideos() as $video) {
                $totalDurationSeconds += $video->getDuration();
            }
            $durationLabel = round($totalDurationSeconds / 60).' min';
        }

        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(0, 150);
        $pdf->Cell($pageWidth, 10, $this->encode('Course Duration : '.$durationLabel), 0, 0, 'C');

        if ($certificate->getTrainerName()) {
            $pdf->SetXY(0, 160);
            $pdf->Cell($pageWidth, 10, $this->encode('Trainer : '.$certificate->getTrainerName()), 0, 0, 'C');
        }

        $pdf->SetXY(100, 178);
        $pdf->Cell(40, 10, $attempt->getCreatedAt()->format('d/m/Y'), 0, 0, 'L');

        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($pageWidth - 70, 10);
        $pdf->Cell(60, 10, 'Ref: '.$certificateNumber, 0, 0, 'R');

        return [
            'content' => $pdf->Output('S'),
            'filename' => 'certificate_'.$safeCertificateNumber.'.pdf',
        ];
    }

    private function encode(string $value): string
    {
        $encoded = iconv('UTF-8', 'windows-1252//TRANSLIT', $value);

        return $encoded === false ? $value : $encoded;
    }
}
