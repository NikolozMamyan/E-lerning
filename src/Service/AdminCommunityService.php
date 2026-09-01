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
        private readonly JobApplicationCvStorage $cvStorage,
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

    public function updateJobOffer(): void
    {
        $this->entityManager->flush();
    }

    public function updateEvent(): void
    {
        $this->entityManager->flush();
    }

    public function deleteJobOffer(JobOffer $jobOffer): void
    {
        $cvFilenames = [];
        foreach ($jobOffer->getApplications() as $application) {
            if ($application->getCvFilename() !== null) {
                $cvFilenames[] = $application->getCvFilename();
            }
        }

        $this->entityManager->remove($jobOffer);
        $this->entityManager->flush();

        foreach ($cvFilenames as $filename) {
            $this->cvStorage->remove($filename);
        }
    }

    public function deleteEvent(CommunityEvent $event): void
    {
        $this->entityManager->remove($event);
        $this->entityManager->flush();
    }
}
