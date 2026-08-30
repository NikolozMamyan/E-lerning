<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommunityEvent;
use App\Entity\JobOffer;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdminCommunityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobOfferRepository $jobOfferRepository,
    ) {
    }

    public function publishJobOffer(JobOffer $jobOffer): void
    {
        if ($jobOffer->isActive()) {
            $this->jobOfferRepository->deactivateAllExcept($jobOffer);
        }

        $this->entityManager->persist($jobOffer);
        $this->entityManager->flush();
    }

    public function publishEvent(CommunityEvent $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function toggleJobOffer(JobOffer $jobOffer): void
    {
        $jobOffer->setIsActive(!$jobOffer->isActive());
        if ($jobOffer->isActive()) {
            $this->jobOfferRepository->deactivateAllExcept($jobOffer);
        }
        $this->entityManager->flush();
    }

    public function toggleEvent(CommunityEvent $event): void
    {
        $event->setIsPublished(!$event->isPublished());
        $this->entityManager->flush();
    }
}
