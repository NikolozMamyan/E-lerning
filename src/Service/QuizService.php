<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\QuizAnswer;
use App\Entity\QuizAttempt;
use App\Entity\QuizQuestion;
use App\Entity\User;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

class QuizService
{
    public function __construct(
        private EntityManagerInterface $em,
        private QuizAttemptRepository $quizAttemptRepository,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * @param array<int|string, mixed> $userAnswers
     *
     * @throws \Exception
     */
    public function evaluate(User $user, Course $course, array $userAnswers): QuizAttempt
    {
        $enrollment = $this->em->getRepository(Enrollment::class)
            ->findOneBy(['user' => $user, 'course' => $course], ['createdAt' => 'DESC']);

        if (!$enrollment && !$user->hasActiveSubscription()) {
            throw new \Exception('Vous devez être abonné ou avoir acheté ce cours pour passer le quiz.');
        }

        if ($this->quizAttemptRepository->hasPassedAttempt($user, $course)) {
            throw new \Exception('Vous avez déjà réussi ce quiz.');
        }

        $criteria = ['user' => $user, 'course' => $course];
        if ($enrollment) {
            $criteria['enrollment'] = $enrollment;
        }

        $existing = $this->em->getRepository(QuizAttempt::class)->findOneBy($criteria);

        if ($existing) {
            throw new \Exception('Vous avez déjà passé ce quiz.');
        }

        $questions = $course->getQuizQuestions();
        $correctCount = 0;

        foreach ($questions as $question) {
            if ($this->evaluateQuestion($question, (array) ($userAnswers[$question->getId()] ?? []))['correct']) {
                $correctCount++;
            }
        }

        $score = count($questions) > 0 ? ($correctCount / count($questions)) * 100 : 0;
        $passed = $score >= 80;

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setCourse($course);

        if ($enrollment) {
            $attempt->setEnrollment($enrollment);
        }

        $attempt->setScore((int) $score);
        $attempt->setPassed($passed);

        $this->em->persist($attempt);
        $this->em->flush();

        return $attempt;
    }

    /**
     * @param array<int|string, mixed> $userAnswers
     * @return list<array{number: int, question: QuizQuestion, selectedAnswers: list<QuizAnswer>}>
     */
    public function getLastIncorrectQuestions(Course $course, array $userAnswers): array
    {
        $incorrectQuestions = [];
        $number = 0;

        foreach ($course->getQuizQuestions() as $question) {
            $number++;
            $result = $this->evaluateQuestion($question, (array) ($userAnswers[$question->getId()] ?? []));

            if (!$result['correct']) {
                $incorrectQuestions[] = [
                    'number' => $number,
                    'question' => $question,
                    'selectedAnswers' => $result['selectedAnswers'],
                ];
            }
        }

        return array_slice($incorrectQuestions, -5);
    }

    /**
     * @return array{correct: bool, selectedAnswers: list<QuizAnswer>}
     */
    private function evaluateQuestion(QuizQuestion $question, array $givenAnswerIds): array
    {
        $validAnswerIds = array_filter($givenAnswerIds, static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)));
        $validSelection = count($validAnswerIds) === count($givenAnswerIds);
        $givenAnswerIds = array_unique(array_map('intval', $validAnswerIds));
        $correctAnswerIds = [];
        $selectedAnswers = [];

        foreach ($question->getAnswers() as $answer) {
            if ($answer->isCorrect()) {
                $correctAnswerIds[] = $answer->getId();
            }
            if (in_array($answer->getId(), $givenAnswerIds, true)) {
                $selectedAnswers[] = $answer;
            }
        }

        sort($givenAnswerIds);
        sort($correctAnswerIds);

        return [
            'correct' => $validSelection && $correctAnswerIds !== [] && $givenAnswerIds === $correctAnswerIds,
            'selectedAnswers' => $selectedAnswers,
        ];
    }

    public function canAttempt(User $user, Course $course): bool
    {
        $latestAttempt = $this->quizAttemptRepository->findLatestForUserAndCourse($user, $course);

        if ($latestAttempt !== null) {
            return false;
        }

        if ($user->hasActiveSubscription()) {
            return true;
        }

        $enrollment = $this->em->getRepository(Enrollment::class)
            ->findOneBy(['user' => $user, 'course' => $course], ['createdAt' => 'DESC']);

        if (!$enrollment) {
            return false;
        }

        $existing = $this->em->getRepository(QuizAttempt::class)
            ->findOneBy(['enrollment' => $enrollment]);

        return !$existing;
    }
}
