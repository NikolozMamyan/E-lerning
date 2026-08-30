<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CommunityEvent;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Service\CommunityEngagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/community', name: 'community_')]
final class CommunityEngagementController extends AbstractController
{
    #[Route('/jobs/{id}/apply', name: 'job_apply', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function apply(JobOffer $jobOffer, Request $request, CommunityEngagementService $engagementService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('warning', 'Sign in or create an account to submit your CV.');

            return $this->redirectToRoute('show_login');
        }

        if (!$this->isCsrfTokenValid('job_apply_'.$jobOffer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid application request.');
        }

        $cv = $request->files->get('cv');
        if (!$cv instanceof UploadedFile) {
            $this->addFlash('error', 'Choose a PDF, DOC or DOCX CV.');

            return $this->feedRedirect('job-offer');
        }

        try {
            $created = $engagementService->apply($jobOffer, $user, $cv);
            $this->addFlash($created ? 'success' : 'warning', $created ? 'Your CV has been submitted.' : 'You already applied for this opportunity.');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->feedRedirect('job-offer');
    }

    #[Route('/events/{id}/register', name: 'event_register', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function register(CommunityEvent $event, Request $request, CommunityEngagementService $engagementService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('warning', 'Sign in or create an account to register for this event.');

            return $this->redirectToRoute('show_login');
        }

        if (!$this->isCsrfTokenValid('event_register_'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid registration request.');
        }

        try {
            $created = $engagementService->register($event, $user);
            $this->addFlash($created ? 'success' : 'warning', $created ? 'Your place is confirmed.' : 'You are already registered for this event.');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->feedRedirect('community-agenda');
    }

    private function feedRedirect(string $fragment): Response
    {
        return $this->redirect($this->generateUrl('article_feed').'#'.$fragment);
    }
}
