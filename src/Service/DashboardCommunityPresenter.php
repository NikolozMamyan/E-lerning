<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\CommunityEventRegistrationRepository;
use App\Repository\CommunityEventRepository;
use App\Repository\JobApplicationRepository;
use App\Repository\JobOfferRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final readonly class DashboardCommunityPresenter
{
    public function __construct(
        private JobOfferRepository $jobOffers,
        private JobApplicationRepository $applications,
        private CommunityEventRepository $events,
        private CommunityEventRegistrationRepository $registrations,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.community_content_sanitizer')]
        private HtmlSanitizerInterface $contentSanitizer,
    ) {
    }

    public function present(User $user): array
    {
        $appliedIds = $this->applications->findJobOfferIdsForApplicant($user);
        $registeredIds = $this->registrations->findEventIdsForMember($user);
        $jobs = [];
        foreach ($this->jobOffers->findActive() as $job) {
            $jobs[] = [
                'id' => $job->getId(),
                'title' => $job->getTitle(),
                'description' => $this->content((string) $job->getDescription()),
                'hasApplied' => in_array($job->getId(), $appliedIds, true),
            ];
        }
        $events = [];
        foreach ($this->events->findUpcoming(10) as $event) {
            $events[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $this->content((string) $event->getDescription()),
                'startsAt' => $event->getStartsAt()?->format(DATE_ATOM),
                'location' => $event->getLocation(),
                'isRegistered' => in_array($event->getId(), $registeredIds, true),
                'registrationsCount' => $event->getRegistrations()->count(),
            ];
        }

        return ['jobOffers' => $jobs, 'upcomingEvents' => $events];
    }

    private function content(string $description): string
    {
        return strip_tags($description) === $description
            ? nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            : $this->contentSanitizer->sanitize($description);
    }
}
