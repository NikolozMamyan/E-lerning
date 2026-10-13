<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Progress;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ProgressRepository;
use App\Repository\QuizAttemptRepository;
use App\Service\LearningProgressService;
use PHPUnit\Framework\TestCase;

final class LearningProgressServiceTest extends TestCase
{
    /** @dataProvider singleVideoProgress */
    public function testPurchasedCoursePreservesWebCompletionThreshold(int $watched, float $expected): void
    {
        $user = new User();
        $course = new Course();
        $video = (new Video())->setDuration(100);
        $course->addVideo($video);
        $enrollment = (new Enrollment())->setUser($user)->setCourse($course);
        $progress = (new Progress())->setUser($user)->setVideo($video)->setWatchedSeconds($watched);
        $attempt = (new QuizAttempt())->setScore(80)->setPassed(true);

        $courses = $this->createMock(CourseRepository::class);
        $courses->expects(self::never())->method('findAll');
        $enrollments = $this->createMock(EnrollmentRepository::class);
        $enrollments->expects(self::once())->method('findBy')->with(['user' => $user])->willReturn([$enrollment]);
        $progressRepo = $this->createMock(ProgressRepository::class);
        $progressRepo->method('findBy')->willReturn([$progress]);
        $progressRepo->method('findOneBy')->with(['user' => $user, 'video' => $video])->willReturn($progress);
        $attempts = $this->createMock(QuizAttemptRepository::class);
        $attempts->expects(self::once())->method('findOneBy')->with(['user' => $user, 'enrollment' => $enrollment], ['createdAt' => 'DESC'])->willReturn($attempt);

        $data = (new LearningProgressService($courses, $enrollments, $progressRepo, $attempts))->forUser($user);

        self::assertCount(1, $data);
        self::assertSame($expected, $data[0]['progressPercent']);
        self::assertSame(80, $data[0]['quizScore']);
        self::assertTrue($data[0]['certified']);
        self::assertSame($enrollment, $data[0]['enrollment']);
    }

    public static function singleVideoProgress(): iterable
    {
        yield 'not started' => [0, 0.0];
        yield 'still active' => [91, 91.0];
        yield 'web completion threshold' => [92, 100.0];
        yield 'finished' => [100, 100.0];
        yield 'never exceed 100 percent' => [140, 100.0];
    }

    public function testSubscriberUsesCompletedLessonsAndCourseQuiz(): void
    {
        $user = $this->createMock(User::class);
        $user->method('hasActiveSubscription')->willReturn(true);
        $course = new Course();
        $first = (new Video())->setDuration(100);
        $second = (new Video())->setDuration(500);
        $course->addVideo($first)->addVideo($second);
        $progress = (new Progress())->setUser($user)->setVideo($first)->setCompleted(true);
        $courses = $this->createMock(CourseRepository::class);
        $courses->expects(self::once())->method('findAll')->willReturn([$course]);
        $enrollments = $this->createMock(EnrollmentRepository::class);
        $enrollments->expects(self::never())->method('findBy');
        $progressRepo = $this->createMock(ProgressRepository::class);
        $progressRepo->method('findBy')->willReturn([$progress]);
        $progressRepo->expects(self::never())->method('findOneBy');
        $attempts = $this->createMock(QuizAttemptRepository::class);
        $attempts->expects(self::once())->method('findOneBy')->with(['user' => $user, 'course' => $course], ['createdAt' => 'DESC'])->willReturn(null);

        $data = (new LearningProgressService($courses, $enrollments, $progressRepo, $attempts))->forUser($user);

        self::assertSame(50.0, $data[0]['progressPercent']);
        self::assertNull($data[0]['enrollment']);
        self::assertNull($data[0]['quizScore']);
        self::assertFalse($data[0]['certified']);
    }
}
