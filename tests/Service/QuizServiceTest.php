<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Repository\QuizAttemptRepository;
use App\Service\NotificationService;
use App\Service\QuizService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class QuizServiceTest extends TestCase
{
    public function testCanAttemptReturnsFalseWhenUserAlreadyPassedQuiz(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $notificationService = $this->createMock(NotificationService::class);
        $user = $this->createConfiguredMock(User::class, [
            'hasActiveSubscription' => true,
        ]);
        $course = $this->createMock(Course::class);

        $quizAttemptRepository->expects($this->once())
            ->method('hasPassedAttempt')
            ->with($user, $course)
            ->willReturn(true);

        $service = new QuizService($entityManager, $quizAttemptRepository, $notificationService);

        self::assertFalse($service->canAttempt($user, $course));
    }

    public function testEvaluateThrowsWhenUserAlreadyPassedQuiz(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $notificationService = $this->createMock(NotificationService::class);
        $enrollmentRepository = $this->createMock(EntityRepository::class);
        $user = $this->createConfiguredMock(User::class, [
            'hasActiveSubscription' => true,
        ]);
        $course = $this->createMock(Course::class);

        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Enrollment::class)
            ->willReturn($enrollmentRepository);

        $enrollmentRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user, 'course' => $course], ['createdAt' => 'DESC'])
            ->willReturn(null);

        $quizAttemptRepository->expects($this->once())
            ->method('hasPassedAttempt')
            ->with($user, $course)
            ->willReturn(true);

        $service = new QuizService($entityManager, $quizAttemptRepository, $notificationService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Vous avez déjà réussi ce quiz.');

        $service->evaluate($user, $course, []);
    }
}
