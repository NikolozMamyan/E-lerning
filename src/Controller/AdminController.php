<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Video;
use App\Entity\QuizQuestion;
use App\Entity\QuizAnswer;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('app/admin/courses', name: 'admin_course_')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(CourseRepository $repo): Response
    {
        $courses = $repo->findBy([], ['id' => 'DESC']);

        return $this->render('admin/index.html.twig', [
            'courses' => $courses,
        ]);
    }

#[Route('/new', name: 'new', methods: ['GET','POST'])]
public function new(Request $request, EntityManagerInterface $em): Response
{
    $course = new Course();

    // Préremplir avec une question et une réponse vide
    $question = new QuizQuestion();
    $question->addAnswer(new QuizAnswer());
    $course->addQuizQuestion($question);

    $form = $this->createForm(CourseType::class, $course);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($course);
        $em->flush();

        return $this->redirectToRoute('admin_course_index');
    }

    return $this->render('admin/new.html.twig', [
        'form' => $form,
        'course' => $course,
    ]);
}



    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('admin/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncChildren($course);

            $em->flush();

            $this->addFlash('success', 'Cours mis à jour.');
            return $this->redirectToRoute('admin_course_show', ['id' => $course->getId()]);
        }

        return $this->render('admin/edit.html.twig', [
            'course' => $course,
            'form'   => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_course_'.$course->getId(), (string) $request->request->get('_token'))) {
            $em->remove($course);
            $em->flush();
            $this->addFlash('success', 'Cours supprimé.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_course_index');
    }

    /**
     * Assure les associations côté propriétaire si les FormType n'appellent pas addXxx().
     * (utile pour Videos -> setCourse, QuizQuestions -> setCourse, QuizAnswers -> setQuestion)
     */
    private function syncChildren(Course $course): void
    {
        // Videos
        if (method_exists($course, 'getVideos')) {
            foreach ($course->getVideos() as $video) {
                if ($video instanceof Video) {
                    $video->setCourse($course);
                }
            }
        }

        // Quiz Questions + Answers
        if (method_exists($course, 'getQuizQuestions')) {
            foreach ($course->getQuizQuestions() as $question) {
                if ($question instanceof QuizQuestion) {
                    $question->setCourse($course);

                    if (method_exists($question, 'getAnswers')) {
                        foreach ($question->getAnswers() as $answer) {
                            if ($answer instanceof QuizAnswer) {
                                $answer->setQuestion($question);
                            }
                        }
                    }
                }
            }
        }
    }
}
