<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Repository\QuizAttemptRepository;
use App\Service\QuizService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
    #[Route('app/course/{id}/quiz', name: 'course_quiz')]
    public function quiz(
        Course $course,
        QuizService $quizService,
        QuizAttemptRepository $quizAttemptRepository,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Veuillez vous connecter pour passer le quiz.');

            return $this->redirectToRoute('app_login');
        }

        $hasSubscription = $user->hasActiveSubscription();

        $enrollment = $em->getRepository(Enrollment::class)->findOneBy([
            'user' => $user,
            'course' => $course,
        ]);

        $hasAccess = $hasSubscription || (bool) $enrollment;

        if (!$hasAccess) {
            $this->addFlash('error', 'Vous devez être abonné ou inscrit à ce cours pour accéder au quiz.');

            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
        }

        if (!$quizService->canAttempt($user, $course)) {
            $lastAttempt = $quizAttemptRepository->findLatestPassedForUserAndCourse($user, $course)
                ?? $quizAttemptRepository->findLatestForUserAndCourse($user, $course);

            return $this->render('quiz/already_done.html.twig', [
                'course' => $course,
                'attempt' => $lastAttempt,
            ]);
        }

        $hasFrench = false;
        $hasItalian = false;

        foreach ($course->getQuizQuestions() as $question) {
            if (!empty($question->getQuestionFr())) {
                $hasFrench = true;
            }
            if (!empty($question->getQuestionIt())) {
                $hasItalian = true;
            }

            foreach ($question->getAnswers() as $answer) {
                if (!empty($answer->getTextFr())) {
                    $hasFrench = true;
                }
                if (!empty($answer->getTextIt())) {
                    $hasItalian = true;
                }
            }
        }

        return $this->render('quiz/pass.html.twig', [
            'course' => $course,
            'hasFrench' => $hasFrench,
            'hasItalian' => $hasItalian,
        ]);
    }

    #[Route('app/course/{id}/quiz/submit', name: 'course_quiz_submit', methods: ['POST'])]
    public function submit(Course $course, Request $request, QuizService $quizService): Response
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
            'attempt' => $attempt,
        ]);
    }
}
