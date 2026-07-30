<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QuizAttempt;
use App\Service\QuizAttemptResetter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ResetQuizAttemptController extends AbstractController
{
    #[Route(
        '/admin/quizzes/{id}/reset',
        name: 'admin_quiz_attempt_reset',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function __invoke(
        Request $request,
        QuizAttempt $attempt,
        QuizAttemptResetter $quizAttemptResetter,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(
            'reset_quiz_attempt_'.$attempt->getId(),
            (string) $request->request->get('_token'),
        )) {
            $this->addFlash('error', 'Le jeton de sécurité est invalide. Veuillez réessayer.');

            return $this->redirectToList($request);
        }

        $username = $attempt->getUser()?->getUsername() ?? $attempt->getUser()?->getEmail() ?? 'cet utilisateur';
        $courseTitle = $attempt->getCourse()?->getTitle() ?? 'ce cours';

        try {
            $quizAttemptResetter->resetFailedAttempt($attempt);
            $this->addFlash(
                'success',
                sprintf('%s peut maintenant repasser le quiz « %s ».', $username, $courseTitle),
            );
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToList($request);
    }

    private function redirectToList(Request $request): RedirectResponse
    {
        $parameters = [];
        foreach (['q', 'course', 'date_from', 'date_to', 'sort', 'page'] as $key) {
            $value = $request->query->get($key);
            if (is_scalar($value) && (string) $value !== '') {
                $parameters[$key] = (string) $value;
            }
        }

        return $this->redirectToRoute('admin_quiz_attempt_index', $parameters);
    }
}
