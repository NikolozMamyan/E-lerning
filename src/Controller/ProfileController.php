<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\QuizAttemptRepository;
use App\Service\ProfileCoverStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(
        Request $request,
        ArticleRepository $articleRepository,
        QuizAttemptRepository $quizAttemptRepository,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('show_login');
        }

        return $this->renderProfile($request, $user, $articleRepository, $quizAttemptRepository, true);
    }

    #[Route('/profile/cover', name: 'app_profile_cover_upload', methods: ['POST'])]
    public function uploadCover(Request $request, ProfileCoverStorage $coverStorage): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('show_login');
        }

        if (!$this->isCsrfTokenValid('profile_cover', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The security token expired. Please try again.');

            return $this->redirectToRoute('app_profile');
        }

        $uploadedFile = $request->files->get('cover');
        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'Select an image for your profile cover.');

            return $this->redirectToRoute('app_profile');
        }

        try {
            $coverStorage->save($user, $uploadedFile);
            $this->addFlash('success', 'Your profile cover has been updated.');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/{id<\d+>}', name: 'app_public_profile', methods: ['GET'])]
    public function show(
        User $profileUser,
        Request $request,
        ArticleRepository $articleRepository,
        QuizAttemptRepository $quizAttemptRepository,
    ): Response {
        if (in_array('ROLE_ADMIN', $profileUser->getRoles(), true)) {
            throw $this->createNotFoundException();
        }

        $currentUser = $this->getUser();

        return $this->renderProfile(
            $request,
            $profileUser,
            $articleRepository,
            $quizAttemptRepository,
            $currentUser instanceof User && $currentUser->getId() === $profileUser->getId(),
        );
    }

    private function renderProfile(
        Request $request,
        User $profileUser,
        ArticleRepository $articleRepository,
        QuizAttemptRepository $quizAttemptRepository,
        bool $isOwnProfile,
    ): Response {
        $sort = strtolower((string) $request->query->get('sort', 'latest'));
        $sort = in_array($sort, ['latest', 'trending', 'discussed'], true) ? $sort : 'latest';

        return $this->render('profile/index.html.twig', [
            'profileUser' => $profileUser,
            'posts' => $articleRepository->findForProfile($profileUser, $sort),
            'postStats' => $articleRepository->getAuthorStats($profileUser),
            'certificates' => $quizAttemptRepository->findPassedByUser($profileUser, 4),
            'certificateCount' => $quizAttemptRepository->countPassedCoursesByUser($profileUser),
            'activeSort' => $sort,
            'isOwnProfile' => $isOwnProfile,
        ]);
    }
}
