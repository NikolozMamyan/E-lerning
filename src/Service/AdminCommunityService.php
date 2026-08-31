<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommunityEvent;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class AdminCommunityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function publishJobOffer(JobOffer $jobOffer): void
    {
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
        $this->entityManager->flush();
    }

    public function toggleEvent(CommunityEvent $event): void
    {
        $event->setIsPublished(!$event->isPublished());
        $this->entityManager->flush();
    }
}
