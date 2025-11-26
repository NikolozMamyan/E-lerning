<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\Course;
use App\Form\CourseType;
use App\Entity\Enrollment;
use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('/courses', name: 'course_index', methods: ['GET'])]
    public function index(CourseRepository $repo): Response
    {
        $courses = $repo->findBy([], ['id' => 'DESC']);

        return $this->render('admin/index.html.twig', [
            'courses' => $courses,
        ]);
    }

#[Route('/courses/new', name: 'course_new', methods: ['GET','POST'])]
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



    #[Route('/courses/{id}', name: 'course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('admin/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/courses/{id}/edit', name: 'course_edit', methods: ['GET', 'POST'])]
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

    #[Route('/courses/{id}', name: 'course_delete', methods: ['POST'])]
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



    #[Route('/enrollments', name: 'enrollments', methods: ['GET'])]
public function listEnrollments(EntityManagerInterface $em): Response
{
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    $enrollments = $em->getRepository(Enrollment::class)->findBy([], ['id' => 'DESC']);

    return $this->render('admin/enrollments.html.twig', [
        'enrollments' => $enrollments,
    ]);
}

#[Route('/enrollments/manage', name: 'enrollments_manage', methods: ['GET'])]
public function manageEnrollments(Request $request, EntityManagerInterface $em): Response
{
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    // Recherche utilisateur
    $email = $request->query->get('email');

    $userRepo = $em->getRepository(User::class);
    $courseRepo = $em->getRepository(Course::class);

    $users = $email
        ? $userRepo->createQueryBuilder('u')
            ->where('u.email LIKE :email')
            ->setParameter('email', '%' . $email . '%')
            ->getQuery()
            ->getResult()
        : $userRepo->findBy([], ['id' => 'DESC']);

    $courses = $courseRepo->findBy([], ['id' => 'DESC']);

    return $this->render('admin/enrollments_manage.html.twig', [
        'users' => $users,
        'courses' => $courses,
        'email' => $email,
    ]);
}


#[Route('/enrollment/new/{courseId}/{userId}', name: 'enrollment_new', methods: ['POST', 'GET'])]
public function createEnrollment(
    int $courseId,
    int $userId,
    EntityManagerInterface $em
): Response {

    $admin = $this->getUser();
if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
    return $this->redirectToRoute('show_login');
}
    $user = $em->getRepository(User::class)->find($userId);
    $course = $em->getRepository(Course::class)->find($courseId);

    if (!$user || !$course) {
        $this->addFlash('danger', 'Utilisateur ou cours introuvable.');
        return $this->redirectToRoute('admin_enrollments');
    }

    // Vérifier si déjà inscrit
    $existing = $em->getRepository(Enrollment::class)->findOneBy([
        'user'   => $user,
        'course' => $course,
    ]);

    if ($existing) {
        $this->addFlash('info', 'Cet utilisateur est déjà inscrit à ce cours.');
        return $this->redirectToRoute('admin_enrollments');
    }

    // Création enrollment
    $enrollment = new Enrollment();
    $enrollment->setUser($user);
    $enrollment->setCourse($course);

    $em->persist($enrollment);
    $em->flush();

    $this->addFlash('success', "Inscription effectuée pour {$user->getEmail()}.");

    return $this->redirectToRoute('admin_enrollments');
}


#[Route('/enrollment/bulk', name: 'enrollment_bulk', methods: ['POST'])]
public function bulkEnrollment(Request $request, EntityManagerInterface $em): Response
{
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    $courseId = $request->request->get('courseId');
    $userIds = $request->request->all('userIds');

    if (!$courseId || empty($userIds)) {
        $this->addFlash('danger', 'Données manquantes.');
        return $this->redirectToRoute('admin_enrollments_manage');
    }

    $course = $em->getRepository(Course::class)->find($courseId);
    if (!$course) {
        $this->addFlash('danger', 'Cours introuvable.');
        return $this->redirectToRoute('admin_enrollments_manage');
    }

    $successCount = 0;
    $skipCount = 0;

    foreach ($userIds as $userId) {
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) continue;

        // Vérifier si déjà inscrit
        $existing = $em->getRepository(Enrollment::class)->findOneBy([
            'user' => $user,
            'course' => $course,
        ]);

        if ($existing) {
            $skipCount++;
            continue;
        }

        $enrollment = new Enrollment();
        $enrollment->setUser($user);
        $enrollment->setCourse($course);
        $em->persist($enrollment);
        $successCount++;
    }

    $em->flush();

    if ($successCount > 0) {
        $this->addFlash('success', "$successCount inscription(s) effectuée(s) avec succès.");
    }
    if ($skipCount > 0) {
        $this->addFlash('info', "$skipCount utilisateur(s) déjà inscrit(s) à ce cours.");
    }

    return $this->redirectToRoute('admin_enrollments');
}


}
