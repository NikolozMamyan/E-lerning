<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\QuizAttempt;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class QuizService
{
    private NotificationService $notificationService;
    private LoggerInterface $logger;

    public function __construct(
        private EntityManagerInterface $em,
        NotificationService $notificationService,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    /**
     * Évalue un quiz pour un utilisateur et un cours.
     *
     * @param User $user
     * @param Course $course
     * @param array $userAnswers tableau [questionId => answerId]
     * @throws \Exception si pas d'enrollment ou si quiz déjà passé
     */
    public function evaluate(User $user, Course $course, array $userAnswers): QuizAttempt
    {
        // Vérifier l'enrollment actif (dernier achat)
        $enrollment = $this->em->getRepository(Enrollment::class)
            ->findOneBy(['user' => $user, 'course' => $course], ['createdAt' => 'DESC']);

        if (!$enrollment) {
            throw new \Exception("Vous devez acheter ce cours pour passer le quiz.");
        }

        // Vérifier si le quiz a déjà été passé
        $existing = $this->em->getRepository(QuizAttempt::class)
            ->findOneBy(['enrollment' => $enrollment]);

        if ($existing) {
            throw new \Exception("Vous avez déjà passé le quiz avec cet achat. Rachetez le cours pour retenter.");
        }

        // Évaluation des réponses
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

        $score = (count($questions) > 0) ? ($correctCount / count($questions)) * 100 : 0;
        $passed = $score >= 80;

        // Sauvegarde de la tentative
        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setCourse($course);
        $attempt->setEnrollment($enrollment);
        $attempt->setScore((int)$score);
        $attempt->setPassed($passed);

        $this->em->persist($attempt);
        $this->em->flush();

        // Notification et log
        if ($passed) {
            try {
                $this->notificationService->createEntityNotification(
                    $user,
                    '🎉 Congratulations !',
                    $enrollment,
                    sprintf(
                       "You passed the quiz for the course \"{$course->getTitle()}\" with a score of {$score}%. Well done!",
                    ),
                    Notification::TYPE_SUCCESS,
                    '/app/course/' . $course->getId(),
                    'quiz-success',
                    Notification::PRIORITY_HIGH
                );

                $this->logger->info('Quiz success notification created', [
                    'userId' => $user->getId(),
                    'courseId' => $course->getId(),
                    'score' => $score
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create quiz success notification', [
                    'userId' => $user->getId(),
                    'courseId' => $course->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $attempt;
    }

    /**
     * Vérifie si un utilisateur peut passer le quiz (et retourne l'enrollment).
     */
    public function canAttempt(User $user, Course $course): ?Enrollment
    {
        $enrollment = $this->em->getRepository(Enrollment::class)
            ->findOneBy(['user' => $user, 'course' => $course], ['createdAt' => 'DESC']);

        if (!$enrollment) {
            return null; // pas d'achat
        }

        $existing = $this->em->getRepository(QuizAttempt::class)
            ->findOneBy(['enrollment' => $enrollment]);

        return $existing ? null : $enrollment;
    }
}
