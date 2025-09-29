<?php

namespace App\Controller\Company;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CompanyCoursesController extends AbstractController
{
    #[Route('company/courses', name: 'company_courses')]
    public function index(
        CourseRepository $courseRepo,
        ProgressRepository $progressRepo,
        EnrollmentRepository $enrollmentRepo
    ): Response {
        $user = $this->getUser();
        $courses = $courseRepo->findAll();

        // Préparer tableau des accès
        $access = [];
        if ($user) {
            foreach ($courses as $course) {
                $enrollment = $enrollmentRepo->findOneBy([
                    'user' => $user,
                    'course' => $course,
                ]);
                $access[$course->getId()] = (bool) $enrollment;
            }
        }
        $collaborations = $user->getCollaborationsAsCompany();

        return $this->render('company/courses/index.html.twig', [
            'courses' => $courses,
            'access' => $access,
            'collaborations' =>$collaborations
        ]);
    }

    #[Route('/company/course/{id}', name: 'company_course_assign', methods: ['POST'])]
    public function assign(
        int $id,
        Request $request,
        CourseRepository $courseRepo,
        UserRepository $userRepo,
        EnrollmentRepository $enrollmentRepo,
        EntityManagerInterface $em
    ): Response {
        $course = $courseRepo->find($id);
        if (!$course) {
            // Si c'est une requête AJAX, retourner JSON
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }
            throw $this->createNotFoundException('Course not found');
        }

        // Récupérer l'email du formulaire
        $email = $request->request->get('email');
        if (!$email) {
            $message = 'Veuillez entrer une adresse email.';
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $message
                ]);
            }
            $this->addFlash('error', $message);
            return $this->redirectToRoute('company_courses');
        }

        // Chercher l'utilisateur par email
        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            $message = 'Aucun utilisateur trouvé avec cet email.';
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $message
                ]);
            }
            $this->addFlash('error', $message);
            return $this->redirectToRoute('company_courses');
        }

        // Vérifier si déjà inscrit
        $existing = $enrollmentRepo->findOneBy([
            'user' => $user,
            'course' => $course,
        ]);
        if ($existing) {
            $message = 'Cet utilisateur est déjà inscrit à ce cours.';
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $message
                ]);
            }
            $this->addFlash('info', $message);
            return $this->redirectToRoute('company_courses');
        }

        // Créer l'inscription
        $enrollment = new Enrollment();
        $enrollment->setUser($user);
        $enrollment->setCourse($course);
        $em->persist($enrollment);
        $em->flush();

        $message = sprintf('Le cours "%s" a été assigné à %s.', $course->getTitle(), $email);
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $message
            ]);
        }

        $this->addFlash('success', $message);
        return $this->redirectToRoute('company_courses');
    }
}