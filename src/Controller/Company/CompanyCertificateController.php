<?php

declare(strict_types=1);

namespace App\Controller\Company;

use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\QuizAttemptRepository;
use App\Service\CertificateArchiveGenerator;
use App\Service\CertificatePdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/company/certificates', name: 'company_certificates_')]
#[IsGranted('ROLE_COMPANY')]
final class CompanyCertificateController extends AbstractController
{
    private const MAX_BULK_DOWNLOAD = 100;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        QuizAttemptRepository $quizAttemptRepository,
        CourseRepository $courseRepository,
    ): Response {
        $company = $this->getCompany();
        $courseId = $this->readCourseId($request);
        $employeeEmail = mb_substr(trim((string) $request->query->get('email', '')), 0, 180);
        $status = (string) $request->query->get('status', 'all');
        if (!in_array($status, ['all', 'passed', 'failed'], true)) {
            $status = 'all';
        }

        $passedFilter = match ($status) {
            'passed' => true,
            'failed' => false,
            default => null,
        };

        $allAttempts = $quizAttemptRepository->findLatestForCompany($company);
        $attempts = $courseId !== null || $employeeEmail !== '' || $passedFilter !== null
            ? $quizAttemptRepository->findLatestForCompany($company, $courseId, $employeeEmail, $passedFilter)
            : $allAttempts;
        $passedCount = count(array_filter(
            $allAttempts,
            static fn (QuizAttempt $attempt): bool => $attempt->isPassed(),
        ));

        return $this->render('company/certificates/index.html.twig', [
            'attempts' => $attempts,
            'courses' => $courseRepository->findBy([], ['title' => 'ASC']),
            'filters' => [
                'courseId' => $courseId,
                'email' => $employeeEmail,
                'status' => $status,
            ],
            'stats' => [
                'total' => count($allAttempts),
                'passed' => $passedCount,
                'failed' => count($allAttempts) - $passedCount,
            ],
        ]);
    }

    #[Route('/{id}/download', name: 'download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function download(
        int $id,
        QuizAttemptRepository $quizAttemptRepository,
        CertificatePdfGenerator $certificatePdfGenerator,
    ): Response {
        $attempt = $quizAttemptRepository->findPassedForCompanyByIds($this->getCompany(), [$id])[0] ?? null;
        if (!$attempt instanceof QuizAttempt) {
            throw $this->createNotFoundException('Certificate not found for this company.');
        }

        $document = $certificatePdfGenerator->generate($attempt);
        $response = new Response($document['content'], Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document['filename']),
        );

        return $response;
    }

    #[Route('/download', name: 'bulk_download', methods: ['POST'])]
    public function bulkDownload(
        Request $request,
        QuizAttemptRepository $quizAttemptRepository,
        CertificateArchiveGenerator $certificateArchiveGenerator,
    ): Response {
        if (!$this->isCsrfTokenValid('company_certificates_bulk', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $attemptIds = $this->readAttemptIds($request);
        if ($attemptIds === []) {
            $this->addFlash('warning', 'Select at least one successful certificate.');

            return $this->redirectToRoute('company_certificates_index');
        }

        if (count($attemptIds) > self::MAX_BULK_DOWNLOAD) {
            $this->addFlash('error', sprintf('You can download up to %d certificates at once.', self::MAX_BULK_DOWNLOAD));

            return $this->redirectToRoute('company_certificates_index');
        }

        $attempts = $quizAttemptRepository->findPassedForCompanyByIds($this->getCompany(), $attemptIds);
        if ($attempts === []) {
            throw $this->createNotFoundException('No downloadable certificates were found for this company.');
        }

        $archivePath = $certificateArchiveGenerator->generate($attempts);

        $response = new BinaryFileResponse($archivePath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'employee-certificates-'.date('Y-m-d').'.zip',
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function getCompany(): User
    {
        $company = $this->getUser();
        if (!$company instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in as a company.');
        }

        return $company;
    }

    private function readCourseId(Request $request): ?int
    {
        $value = trim((string) $request->query->get('course', ''));

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** @return int[] */
    private function readAttemptIds(Request $request): array
    {
        $ids = [];
        foreach ($request->request->all('attempt_ids') as $value) {
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
