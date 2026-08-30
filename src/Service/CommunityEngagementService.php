<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommunityEvent;
use App\Entity\CommunityEventRegistration;
use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Repository\CommunityEventRegistrationRepository;
use App\Repository\JobApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CommunityEngagementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly CommunityEventRegistrationRepository $eventRegistrationRepository,
        private readonly JobApplicationCvStorage $cvStorage,
    ) {
    }

    public function apply(JobOffer $jobOffer, User $applicant, UploadedFile $cv): bool
    {
        if (!$jobOffer->isActive()) {
            throw new \RuntimeException('This opportunity is no longer accepting applications.');
        }

        if ($this->jobApplicationRepository->findOneBy(['jobOffer' => $jobOffer, 'applicant' => $applicant])) {
            return false;
        }

        $storedFilename = $this->cvStorage->store($cv);
        $application = (new JobApplication())
            ->setJobOffer($jobOffer)
            ->setApplicant($applicant)
            ->setCvFilename($storedFilename)
            ->setOriginalFilename($this->safeOriginalFilename($cv));

        try {
            $this->entityManager->persist($application);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->cvStorage->remove($storedFilename);
            throw new \RuntimeException('The application could not be saved.', previous: $exception);
        }

        return true;
    }

    public function register(CommunityEvent $event, User $member): bool
    {
        if (!$event->isPublished() || $event->getStartsAt() < new \DateTimeImmutable()) {
            throw new \RuntimeException('Registration for this event is closed.');
        }

        if ($this->eventRegistrationRepository->findOneBy(['communityEvent' => $event, 'member' => $member])) {
            return false;
        }

        $registration = (new CommunityEventRegistration())
            ->setCommunityEvent($event)
            ->setMember($member);

        try {
            $this->entityManager->persist($registration);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('The event registration could not be saved.', previous: $exception);
        }

        return true;
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $name = trim($file->getClientOriginalName());
        $name = preg_replace('/[^a-zA-Z0-9._ -]/', '', $name) ?: 'cv';

        return mb_substr($name, 0, 255);
    }
}
