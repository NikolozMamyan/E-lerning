<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\QuizAttempt;
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
            $givenAnswerId = $userAnswers[$question->getId()] ?? null;

            foreach ($question->getAnswers() as $answer) {
                if ($answer->isCorrect() && $givenAnswerId == $answer->getId()) {
                    $correctCount++;
                }
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
