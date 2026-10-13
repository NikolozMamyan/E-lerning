<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CommunityEvent;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Service\CommunityEngagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CommunityEngagementApiController extends AbstractController
{
    #[Route('/api/community/jobs/{id}/apply', name: 'api_community_job_apply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function apply(JobOffer $jobOffer, Request $request, CommunityEngagementService $engagement): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], 401);
        }
        $cv = $request->files->get('cv');
        if (!$cv instanceof UploadedFile) {
            return $this->json(['message' => 'Select a CV to submit.'], 422);
        }
        try {
            $created = $engagement->apply($jobOffer, $user, $cv);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }

        return $this->json(['success' => true, 'hasApplied' => true], $created ? 201 : 200);
    }

    #[Route('/api/community/events/{id}/register', name: 'api_community_event_register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function register(CommunityEvent $event, CommunityEngagementService $engagement): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], 401);
        }
        try {
            $created = $engagement->register($event, $user);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }

        return $this->json(['success' => true, 'isRegistered' => true], $created ? 201 : 200);
    }
}
