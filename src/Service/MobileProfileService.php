<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProfessionalExperience;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\QuizAttemptRepository;

final readonly class MobileProfileService
{
    public function __construct(private ArticleRepository $articles, private QuizAttemptRepository $attempts, private LearnerArticlePresenter $articlePresenter) {}

    public function details(User $user, bool $own): array
    {
        return [
            'id' => $user->getId(), 'username' => $user->getUsername(), 'email' => $own ? $user->getEmail() : null,
            'phone' => $own ? $user->getPhone() : null, 'bio' => $user->getBio(),
            'role' => in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'Administrator' : (in_array('ROLE_COMPANY', $user->getRoles(), true) ? 'Company' : 'Learner'),
            'avatarUrl' => $user->getAvatar() ? '/uploads/avatars/'.$user->getAvatar() : null,
            'coverUrl' => $user->getCoverImage() ? '/uploads/covers/'.$user->getCoverImage() : null,
            'location' => ['country' => $user->getCountry(), 'city' => $user->getCity(), 'postalCode' => $own ? $user->getPostalCode() : null],
            'company' => ['taxId' => $own ? $user->getTaxId() : null],
            'education' => ['bachelorDegree' => $user->getBachelorDegree(), 'masterDegree' => $user->getMasterDegree()],
            'professionalExperiences' => array_map(static fn (ProfessionalExperience $experience): array => [
                'id' => $experience->getId(), 'title' => $experience->getTitle(), 'companyName' => $experience->getCompanyName(),
                'employmentType' => $experience->getEmploymentType(), 'startDate' => $experience->getStartDate()?->format('Y-m-d'),
                'endDate' => $experience->getEndDate()?->format('Y-m-d'), 'isCurrent' => $experience->isCurrent(),
                'location' => $experience->getLocation(), 'workplaceType' => $experience->getWorkplaceType(), 'description' => $experience->getDescription(),
            ], $user->getProfessionalExperiences()->toArray()),
        ];
    }

    public function profile(User $profile, User $viewer, string $sort): array
    {
        $own = $profile->getId() === $viewer->getId();
        $sort = in_array($sort, ['latest', 'trending', 'discussed'], true) ? $sort : 'latest';
        return [
            'profile' => $this->details($profile, $own), 'isOwnProfile' => $own, 'sort' => $sort,
            'postStats' => $this->articles->getAuthorStats($profile),
            'certificateCount' => $this->attempts->countPassedCoursesByUser($profile),
            'posts' => array_map(fn ($article): array => $this->articlePresenter->present($article, $viewer), $this->articles->findForProfile($profile, $sort)),
            'certificates' => array_map(static fn ($attempt): array => ['attemptId' => $attempt->getId(), 'score' => $attempt->getScore(), 'createdAt' => $attempt->getCreatedAt()->format(DATE_ATOM), 'course' => ['id' => $attempt->getCourse()?->getId(), 'title' => $attempt->getCourse()?->getTitle(), 'image' => $attempt->getCourse()?->getThumb()]], $this->attempts->findPassedByUser($profile, 4)),
        ];
    }
}
