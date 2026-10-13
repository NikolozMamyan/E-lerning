<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\QuizAnswer;
use App\Entity\QuizAttempt;
use App\Entity\QuizQuestion;
use App\Entity\User;
use App\Repository\QuizAttemptRepository;
use App\Service\NotificationService;
use App\Service\QuizService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class QuizServiceTest extends TestCase
{
    /**
     * @dataProvider answerSelectionProvider
     */
    public function testEvaluateRequiresAllCorrectAnswersAndNoOthers(array $userAnswers, int $expectedScore): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $notificationService = $this->createMock(NotificationService::class);
        $enrollmentRepository = $this->createMock(EntityRepository::class);
        $attemptRepository = $this->createMock(EntityRepository::class);
        $user = $this->createConfiguredMock(User::class, [
            'hasActiveSubscription' => true,
        ]);
        $multipleChoiceQuestion = $this->createConfiguredMock(QuizQuestion::class, [
            'getId' => 1,
            'getAnswers' => new ArrayCollection([
                $this->createConfiguredMock(QuizAnswer::class, ['getId' => 11, 'isCorrect' => true]),
                $this->createConfiguredMock(QuizAnswer::class, ['getId' => 12, 'isCorrect' => true]),
                $this->createConfiguredMock(QuizAnswer::class, ['getId' => 13, 'isCorrect' => false]),
            ]),
        ]);
        $singleChoiceQuestion = $this->createConfiguredMock(QuizQuestion::class, [
            'getId' => 2,
            'getAnswers' => new ArrayCollection([
                $this->createConfiguredMock(QuizAnswer::class, ['getId' => 21, 'isCorrect' => true]),
                $this->createConfiguredMock(QuizAnswer::class, ['getId' => 22, 'isCorrect' => false]),
            ]),
        ]);
        $course = $this->createConfiguredMock(Course::class, [
            'getQuizQuestions' => new ArrayCollection([$multipleChoiceQuestion, $singleChoiceQuestion]),
        ]);

        $entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->willReturnMap([
                [Enrollment::class, $enrollmentRepository],
                [QuizAttempt::class, $attemptRepository],
            ]);
        $enrollmentRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user, 'course' => $course], ['createdAt' => 'DESC'])
            ->willReturn(null);
        $quizAttemptRepository->expects($this->once())
            ->method('hasPassedAttempt')
            ->with($user, $course)
            ->willReturn(false);
        $attemptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user, 'course' => $course])
            ->willReturn(null);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(QuizAttempt::class));
        $entityManager->expects($this->once())->method('flush');

        $service = new QuizService($entityManager, $quizAttemptRepository, $notificationService);
        $attempt = $service->evaluate($user, $course, $userAnswers);

        self::assertSame($expectedScore, $attempt->getScore());
        self::assertSame($expectedScore >= 80, $attempt->isPassed());
    }

    public static function answerSelectionProvider(): array
    {
        return [
            'all correct answers' => [[1 => ['11', '12'], 2 => ['21']], 100],
            'different order' => [[1 => ['12', '11'], 2 => ['21']], 100],
            'duplicate answers count once' => [[1 => ['11', '12', '11'], 2 => ['21']], 100],
            'integer IDs' => [[1 => [11, 12], 2 => [21]], 100],
            'legacy single answer' => [[1 => ['11', '12'], 2 => '21'], 100],
            'missing correct answer' => [[1 => ['11'], 2 => ['21']], 50],
            'extra incorrect answer' => [[1 => ['11', '12', '13'], 2 => ['21']], 50],
            'only incorrect answer' => [[1 => ['13'], 2 => ['21']], 50],
            'answer from another question' => [[1 => ['11', '12', '21'], 2 => ['21']], 50],
            'unknown answer' => [[1 => ['11', '12', '999'], 2 => ['21']], 50],
            'unanswered question' => [[2 => ['21']], 50],
            'empty selection' => [[1 => [], 2 => ['21']], 50],
            'no answers' => [[], 0],
            'nested selection' => [[1 => ['11', ['12']], 2 => ['21']], 50],
            'invalid ID' => [[1 => ['11', '12invalid'], 2 => ['21']], 50],
            'extra invalid ID' => [[1 => ['11', '12', false], 2 => ['21']], 50],
            'extra answer on single-choice question' => [[1 => ['11', '12'], 2 => ['21', '22']], 50],
        ];
    }

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
            ->method('findLatestForUserAndCourse')
            ->with($user, $course)
            ->willReturn($this->createMock(QuizAttempt::class));

        $service = new QuizService($entityManager, $quizAttemptRepository, $notificationService);

        self::assertFalse($service->canAttempt($user, $course));
    }

    public function testCanAttemptReturnsFalseWhenSubscribedUserAlreadyFailedQuiz(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $notificationService = $this->createMock(NotificationService::class);
        $user = $this->createConfiguredMock(User::class, [
            'hasActiveSubscription' => true,
        ]);
        $course = $this->createMock(Course::class);
        $attempt = $this->createConfiguredMock(QuizAttempt::class, [
            'isPassed' => false,
        ]);

        $quizAttemptRepository->expects($this->once())
            ->method('findLatestForUserAndCourse')
            ->with($user, $course)
            ->willReturn($attempt);

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
