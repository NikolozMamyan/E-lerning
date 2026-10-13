<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ProgressRepository;
use App\Repository\QuizAttemptRepository;

final readonly class LearningProgressService
{
    public function __construct(
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private ProgressRepository $progress,
        private QuizAttemptRepository $attempts,
    ) {
    }

    public function forUser(User $user): array
    {
        $hasSubscription = $user->hasActiveSubscription();
        $enrollments = $hasSubscription ? [] : $this->enrollments->findBy(['user' => $user]);
        $courses = $hasSubscription ? $this->courses->findAll() : array_map(static fn ($enrollment) => $enrollment->getCourse(), $enrollments);
        $progressList = $this->progress->findBy(['user' => $user]);
        $data = [];
        foreach ($courses as $course) {
            $videos = $course->getVideos();
            $percent = 0;
            if (count($videos) > 1) {
                $completed = array_filter($progressList, static fn ($progress) => $progress->isCompleted() && $progress->getVideo()->getCourse() === $course);
                $percent = count($completed) / count($videos) * 100;
            } elseif (count($videos) === 1) {
                $video = $videos[0];
                $progress = $this->progress->findOneBy(['user' => $user, 'video' => $video]);
                if ($progress && $video->getDuration() > 0) {
                    $percent = min($progress->getWatchedSeconds() / $video->getDuration() * 100, 100);
                }
            }
            $enrollment = $hasSubscription ? null : (array_values(array_filter($enrollments, static fn ($item) => $item->getCourse() === $course))[0] ?? null);
            $attempt = $this->attempts->findOneBy(
                $hasSubscription ? ['user' => $user, 'course' => $course] : ['user' => $user, 'enrollment' => $enrollment],
                ['createdAt' => 'DESC'],
            );
            $data[] = [
                'course' => $course,
                'enrollment' => $enrollment,
                'progressPercent' => round($percent >= 92 && $percent < 100 ? 100 : $percent, 2),
                'quizScore' => $attempt?->getScore(),
                'certified' => $attempt?->isPassed() ?? false,
            ];
        }

        return $data;
    }
}
