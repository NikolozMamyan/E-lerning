<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\QuizAttemptRepository;
use App\Service\CertificatePdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

final class CertificateController extends AbstractController
{
    #[Route('/app/certificates', name: 'app_certificates')]
    public function certificate(QuizAttemptRepository $quizAttemptRepository): Response
    {
        $user = $this->getUser();
        $userAttempts = $quizAttemptRepository->findByUser($user);
        $passedAttempts = array_values(array_filter(
            $userAttempts,
            static fn ($attempt): bool => $attempt->isPassed(),
        ));
        $totalScore = array_sum(array_map(
            static fn ($attempt): int => $attempt->getScore(),
            $userAttempts,
        ));

        return $this->render('certificates/index.html.twig', [
            'userAttempts' => $userAttempts,
            'featuredAttempt' => $passedAttempts[0] ?? null,
            'certificateStats' => [
                'earned' => count($passedAttempts),
                'attempts' => count($userAttempts),
                'averageScore' => count($userAttempts) > 0
                    ? (int) round($totalScore / count($userAttempts))
                    : 0,
            ],
        ]);
    }

    #[Route('/app/certificate/{id}', name: 'generate_certificate')]
    public function generateCertificate(
        int $id,
        QuizAttemptRepository $quizAttemptRepository,
        CertificatePdfGenerator $certificatePdfGenerator,
    ): Response {
        $user = $this->getUser();
        $attempt = $quizAttemptRepository->find($id);

        if (!$attempt || $attempt->getUser()?->getId() !== $user?->getId()) {
            throw $this->createNotFoundException('Attempt not found for this user.');
        }

        if (!$attempt->isPassed()) {
            $this->addFlash('info', 'This attempt is not passed.');

            return $this->redirectToRoute('app_dashboard');
        }

        $document = $certificatePdfGenerator->generate($attempt);

        $response = new Response($document['content'], Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document['filename']),
        );

        return $response;
    }
}
