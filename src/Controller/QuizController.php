<?php
namespace App\Controller;

use App\Entity\Course;
use App\Entity\QuizAttempt;
use App\Service\QuizService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
  #[Route('app/course/{id}/quiz', name: 'course_quiz')]
public function quiz(
    Course $course,
    QuizService $quizService,
    EntityManagerInterface $em,
    Request $request
) {
    $user = $this->getUser();

    if (!$user) {
        $this->addFlash('error', 'Veuillez vous connecter pour passer le quiz.');
        return $this->redirectToRoute('app_login');
    }

    // ✅ Vérification abonnement et inscription
    $hasSubscription = $user->hasActiveSubscription();

    $enrollmentRepo = $em->getRepository(Enrollment::class);
    $enrollment = $enrollmentRepo->findOneBy([
        'user' => $user,
        'course' => $course,
    ]);
    $hasEnrollment = (bool) $enrollment;

    // ✅ L’utilisateur a accès s’il a un abonnement ou une inscription
    $hasAccess = $hasSubscription || $hasEnrollment;

    if (!$hasAccess) {
        $this->addFlash('error', 'Vous devez être abonné ou inscrit à ce cours pour accéder au quiz.');
        return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
    }

    // ✅ Vérifier s’il peut tenter le quiz
    $canAttempt = $quizService->canAttempt($user, $course);

    if (!$canAttempt) {
        // Vérifier s’il a déjà passé le quiz
        $lastAttempt = $em->getRepository(QuizAttempt::class)
            ->findOneBy(['user' => $user, 'course' => $course], ['id' => 'DESC']);

        return $this->render('quiz/already_done.html.twig', [
            'course' => $course,
            'attempt' => $lastAttempt
        ]);
    }

    // 👉 Gestion de la langue
    $locale = $request->getLocale();
    $hasFrench = false;

    foreach ($course->getQuizQuestions() as $question) {
        if ($locale === 'fr' && $question->getQuestionFr()) {
            $question->setQuestion($question->getQuestionFr());
        }

        foreach ($question->getAnswers() as $answer) {
            if ($locale === 'fr' && $answer->getTextFr()) {
                $answer->setText($answer->getTextFr());
            }
            if (!empty($answer->getTextFr())) {
                $hasFrench = true;
            }
        }

        if (!empty($question->getQuestionFr())) {
            $hasFrench = true;
        }
    }

    return $this->render('quiz/pass.html.twig', [
        'course' => $course,
        'hasFrench' => $hasFrench
    ]);
}



    #[Route('app/course/{id}/quiz/submit', name: 'course_quiz_submit', methods: ['POST'])]
    public function submit(Course $course, Request $request, QuizService $quizService)
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Veuillez vous connecter pour soumettre le quiz.');
            return $this->redirectToRoute('app_login');
        }

        $answers = $request->request->all('answers');

        try {
            $attempt = $quizService->evaluate($user, $course, $answers);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('course_quiz', ['id' => $course->getId()]);
        }

        return $this->render('quiz/result.html.twig', [
            'course' => $course,
            'attempt' => $attempt
        ]);
    }
}
