<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\QuizAttempt;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

class QuizService
{
    public function __construct(private EntityManagerInterface $em, NotificationService $notificationService) 
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Évalue un quiz pour un utilisateur et un cours.
     *
     * @param User  $user
     * @param Course $course
     * @param array $userAnswers tableau [questionId => answerId]
     * @throws \Exception si pas d'enrollment ou si quiz déjà passé
     */
   public function evaluate(User $user, Course $course, array $userAnswers): QuizAttempt
{
    // ✅ Si abonnement, pas besoin d'enrollment
    $enrollment = $this->em->getRepository(Enrollment::class)
        ->findOneBy(['user' => $user, 'course' => $course], ['createdAt' => 'DESC']);

    if (!$enrollment && !$user->hasActiveSubscription()) {
        throw new \Exception("Vous devez être abonné ou avoir acheté ce cours pour passer le quiz.");
    }

    // ✅ Empêcher plusieurs tentatives
    $criteria = ['user' => $user, 'course' => $course];
    if ($enrollment) {
        $criteria['enrollment'] = $enrollment;
    }

    $existing = $this->em->getRepository(QuizAttempt::class)->findOneBy($criteria);

    if ($existing) {
        throw new \Exception("Vous avez déjà passé ce quiz.");
    }

    // ✅ Évaluation des réponses
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

    // ✅ Enregistrer la tentative
    $attempt = new QuizAttempt();
    $attempt->setUser($user);
    $attempt->setCourse($course);
    if ($enrollment) {
        $attempt->setEnrollment($enrollment);
    }
    $attempt->setScore((int)$score);
    $attempt->setPassed($passed);

    $this->em->persist($attempt);
    $this->em->flush();

    return $attempt;
}


    /**
     * Vérifie si un utilisateur peut passer le quiz (et retourne l'enrollment).
     */
public function canAttempt(User $user, Course $course): bool
{
    // ✅ Si abonnement actif → accès direct
    if ($user->hasActiveSubscription()) {
        return true;
    }

    // ✅ Sinon, on cherche une inscription au cours
    $enrollment = $this->em->getRepository(Enrollment::class)
        ->findOneBy(['user' => $user, 'course' => $course], ['createdAt' => 'DESC']);

    if (!$enrollment) {
        return false; // ni abonnement, ni achat
    }

    // ✅ Vérifie s'il a déjà tenté le quiz
    $existing = $this->em->getRepository(QuizAttempt::class)
        ->findOneBy(['enrollment' => $enrollment]);

    return !$existing;
}

}
