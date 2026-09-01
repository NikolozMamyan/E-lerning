<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CommunityEvent;
use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Form\CommunityEventType;
use App\Form\JobOfferType;
use App\Repository\CommunityEventRepository;
use App\Repository\CommunityEventRegistrationRepository;
use App\Repository\JobApplicationRepository;
use App\Repository\JobOfferRepository;
use App\Service\AdminCommunityService;
use App\Service\JobApplicationCvStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/community', name: 'admin_community_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminCommunityController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AdminCommunityService $communityService,
        JobOfferRepository $jobOfferRepository,
        JobApplicationRepository $jobApplicationRepository,
        CommunityEventRepository $eventRepository,
        CommunityEventRegistrationRepository $registrationRepository,
    ): Response {
        $jobOffer = new JobOffer();
        $jobForm = $this->createForm(JobOfferType::class, $jobOffer);
        $jobForm->handleRequest($request);
        if ($jobForm->isSubmitted() && $jobForm->isValid()) {
            $communityService->publishJobOffer($jobOffer);
            $this->addFlash('success', 'The job opportunity is now available in the community feed.');

            return $this->redirectToRoute('admin_community_index');
        }

        $event = new CommunityEvent();
        $eventForm = $this->createForm(CommunityEventType::class, $event);
        $eventForm->handleRequest($request);
        if ($eventForm->isSubmitted() && $eventForm->isValid()) {
            $communityService->publishEvent($event);
            $this->addFlash('success', 'The event has been added to the community agenda.');

            return $this->redirectToRoute('admin_community_index');
        }

        return $this->render('admin/community/index.html.twig', [
            'jobForm' => $jobForm,
            'eventForm' => $eventForm,
            'jobOffers' => $jobOfferRepository->findBy([], ['createdAt' => 'DESC']),
            'applications' => $jobApplicationRepository->findBy([], ['createdAt' => 'DESC']),
            'events' => $eventRepository->findBy([], ['startsAt' => 'DESC']),
            'registrations' => $registrationRepository->findBy([], ['registeredAt' => 'DESC']),
        ]);
    }

    #[Route('/jobs/{id}/toggle', name: 'job_toggle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function toggleJob(JobOffer $jobOffer, Request $request, AdminCommunityService $communityService): Response
    {
        if (!$this->isCsrfTokenValid('toggle_job_'.$jobOffer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $communityService->toggleJobOffer($jobOffer);

        return $this->redirect($this->generateUrl('admin_community_index').'#job-list');
    }

    #[Route('/jobs/{id}/edit', name: 'job_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editJob(JobOffer $jobOffer, Request $request, AdminCommunityService $communityService): Response
    {
        $form = $this->createForm(JobOfferType::class, $jobOffer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $communityService->updateJobOffer();
            $this->addFlash('success', 'The job opportunity has been updated.');

            return $this->redirect($this->generateUrl('admin_community_index').'#job-list');
        }

        return $this->render('admin/community/edit.html.twig', [
            'form' => $form,
            'itemType' => 'job',
            'itemTitle' => $jobOffer->getTitle(),
        ]);
    }

    #[Route('/jobs/{id}/delete', name: 'job_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteJob(JobOffer $jobOffer, Request $request, AdminCommunityService $communityService): Response
    {
        if (!$this->isCsrfTokenValid('delete_job_'.$jobOffer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $communityService->deleteJobOffer($jobOffer);
        $this->addFlash('success', 'The job opportunity and its applications have been deleted.');

        return $this->redirect($this->generateUrl('admin_community_index').'#job-list');
    }

    #[Route('/events/{id}/toggle', name: 'event_toggle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function toggleEvent(CommunityEvent $event, Request $request, AdminCommunityService $communityService): Response
    {
        if (!$this->isCsrfTokenValid('toggle_event_'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $communityService->toggleEvent($event);

        return $this->redirect($this->generateUrl('admin_community_index').'#event-list');
    }

    #[Route('/events/{id}/edit', name: 'event_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editEvent(CommunityEvent $event, Request $request, AdminCommunityService $communityService): Response
    {
        $form = $this->createForm(CommunityEventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $communityService->updateEvent();
            $this->addFlash('success', 'The community event has been updated.');

            return $this->redirect($this->generateUrl('admin_community_index').'#event-list');
        }

        return $this->render('admin/community/edit.html.twig', [
            'form' => $form,
            'itemType' => 'event',
            'itemTitle' => $event->getTitle(),
        ]);
    }

    #[Route('/events/{id}/delete', name: 'event_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteEvent(CommunityEvent $event, Request $request, AdminCommunityService $communityService): Response
    {
        if (!$this->isCsrfTokenValid('delete_event_'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $communityService->deleteEvent($event);
        $this->addFlash('success', 'The community event and its registrations have been deleted.');

        return $this->redirect($this->generateUrl('admin_community_index').'#event-list');
    }

    #[Route('/applications/{id}/cv', name: 'application_cv', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function downloadCv(JobApplication $application, JobApplicationCvStorage $storage): BinaryFileResponse
    {
        $response = new BinaryFileResponse($storage->path((string) $application->getCvFilename()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, (string) $application->getOriginalFilename());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
