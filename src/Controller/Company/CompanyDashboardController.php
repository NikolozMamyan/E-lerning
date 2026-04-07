<?php

namespace App\Controller\Company;


use App\Service\MailerService;
use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;

use App\Entity\User;
use App\Form\PersonalInfoType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\QuizAttemptRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class CompanyDashboardController extends AbstractController{


   #[Route('company/dashboard', name: 'company_dashboard')]
public function index(
    CourseRepository $courseRepo,
    ProgressRepository $progressRepo,
    QuizAttemptRepository $quizAttemptRepo,
    EnrollmentRepository $enrollmentRepo,
    EntityManagerInterface $em
): Response {
    $user = $this->getUser();

    // Tous les cours
    $courses = $courseRepo->findAll();
    $latestCourses = $courseRepo->findBy([], ['updatedAt' => 'DESC'], 3);

    // --- Progression de l’entreprise elle-même (optionnelle) ---
    $progressData = [];
    if ($user) {
        foreach ($courses as $course) {
            $videos = $course->getVideos();
            $totalVideos = count($videos);
            $percent = 0;

            if ($totalVideos > 0) {
                $completedPercent = 0;
                foreach ($videos as $video) {
                    $progress = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                    if ($progress) {
                        $watched = $progress->getWatchedSeconds();
                        $duration = $video->getDuration();
                        if ($duration > 0) {
                            $completedPercent += min(($watched / $duration) * 100, 100);
                        }
                    }
                }
                $percent = round($completedPercent / $totalVideos, 2);
            }

            $progressData[$course->getId()] = $percent;
        }
    }

    // --- Enrollments (transactions) ---
    $enrollments = $user ? $enrollmentRepo->findByUserWithCourse($user) : [];

    // --- Collaborations (employés) ---
    $collaborations = $user->getCollaborationsAsCompany();
    $employees = $collaborations->map(fn($c) => $c->getEmployee());

    // --- Progression des collaborateurs ---
    $collaboratorsProgress = [];

    foreach ($collaborations as $collab) {
        $employee = $collab->getEmployee();
        $employeeProgress = [];

        foreach ($courses as $course) {
            $videos = $course->getVideos();
            $totalVideos = count($videos);
            $percent = 0;

            if ($totalVideos > 0) {
                $completedPercent = 0;
                foreach ($videos as $video) {
                    $progress = $progressRepo->findOneBy([
                        'user' => $employee,
                        'video' => $video
                    ]);

                    if ($progress) {
                        $watched = $progress->getWatchedSeconds();
                        $duration = $video->getDuration();
                        if ($duration > 0) {
                            $completedPercent += min(($watched / $duration) * 100, 100);
                        }
                    }
                }

                $percent = round($completedPercent / $totalVideos, 2);
            }

            $employeeProgress[$course->getId()] = $percent;
        }

        $collaboratorsProgress[] = [
            'employee' => $employee,
            'progress' => $employeeProgress,
        ];
    }

    return $this->render('company/dashboard/index.html.twig', [
        'courses' => $courses,
        'latestCourses' => $latestCourses,
        'progressData' => $progressData,
        'enrollments' => $enrollments,
        'employees' => $employees,
        'collaborations' => $collaborations,
        'collaboratorsProgress' => $collaboratorsProgress,
    ]);
}
#[Route('company/team-tracking', name: 'company_team_tracking')]
public function teamTracking(
    CourseRepository $courseRepo,
    ProgressRepository $progressRepo,
    QuizAttemptRepository $attemptRepo,
    EnrollmentRepository $enrollmentRepo,
    EntityManagerInterface $em
): Response {
    $user = $this->getUser();

    if (!$user) {
        throw $this->createAccessDeniedException('You must be logged in as a company.');
    }

    // Tous les cours = pour le tableau global
    $courses = $courseRepo->findAll();

    // Enrollments de l’entreprise = pour le tableau "Course Results"
    $enrollments = $enrollmentRepo->findByUserWithCourse($user);

    // Extraire les cours possédés par l’entreprise
    $enrolledCourses = [];
    $seenCourseIds = [];

    foreach ($enrollments as $enrollment) {
        $course = $enrollment->getCourse();

        if (!$course) {
            continue;
        }

        if (!in_array($course->getId(), $seenCourseIds, true)) {
            $seenCourseIds[] = $course->getId();
            $enrolledCourses[] = $course;
        }
    }

    // Collaborations (employés)
    $collaborations = $user->getCollaborationsAsCompany();
    $employees = $collaborations->map(fn($c) => $c->getEmployee());

    // --- Tableau global : progression sur tous les cours ---
    $collaboratorsProgress = [];

    foreach ($collaborations as $collab) {
        $employee = $collab->getEmployee();
        $employeeProgress = [];

        foreach ($courses as $course) {
            $videos = $course->getVideos();
            $totalVideos = count($videos);
            $percent = 0;

            if ($totalVideos > 0) {
                $completedPercent = 0;

                foreach ($videos as $video) {
                    $progress = $progressRepo->findOneBy([
                        'user' => $employee,
                        'video' => $video
                    ]);

                    if ($progress) {
                        $watched = $progress->getWatchedSeconds();
                        $duration = $video->getDuration();

                        if ($duration > 0) {
                            $completedPercent += min(($watched / $duration) * 100, 100);
                        }
                    }
                }

                $percent = round($completedPercent / $totalVideos, 2);
            }

            $employeeProgress[$course->getId()] = $percent;
        }

        $average = 0;
        if (count($employeeProgress) > 0) {
            $average = round(array_sum($employeeProgress) / count($employeeProgress), 2);
        }

        $collaboratorsProgress[] = [
            'employee' => $employee,
            'progress' => $employeeProgress,
            'average' => $average,
        ];
    }

    // --- IDs des employés ---
    $employeeIds = [];
    foreach ($collaborations as $collab) {
        $employeeIds[] = $collab->getEmployee()->getId();
    }

    // --- Dernières tentatives des employés ---
    $attempts = !empty($employeeIds) ? $attemptRepo->findLatestByUserIds($employeeIds) : [];

    // --- Indexation des tentatives par userId + courseId ---
    $attemptMap = [];
    foreach ($attempts as $qa) {
        $attemptUser = $qa->getUser();
        $attemptCourse = $qa->getCourse();

        if (!$attemptUser || !$attemptCourse) {
            continue;
        }

        $key = $attemptUser->getId() . '_' . $attemptCourse->getId();
        $attemptMap[$key] = $qa;
    }

    // --- Tableau 2 : résultats seulement sur les cours possédés ---
    $courseResults = [];

    foreach ($employees as $employee) {
        foreach ($enrolledCourses as $course) {
            $key = $employee->getId() . '_' . $course->getId();
            $attempt = $attemptMap[$key] ?? null;

            $courseResults[] = [
                'userId' => $employee->getId(),
                'userName' => $employee->getUsername(),
                'userEmail' => $employee->getEmail(),
                'courseId' => $course->getId(),
                'courseTitle' => $course->getTitle(),
                'score' => $attempt ? $attempt->getScore() : null,
                'passed' => $attempt ? $attempt->isPassed() : false,
                'attempted' => $attempt !== null,
                'attemptedAt' => ($attempt && $attempt->getCreatedAt())
                    ? $attempt->getCreatedAt()->format('Y-m-d H:i:s')
                    : null,
            ];
        }
    }

    return $this->render('company/dashboard/team_tracking.html.twig', [
        'courses' => $courses,
        'enrolledCourses' => $enrolledCourses,
        'enrollments' => $enrollments,
        'employees' => $employees,
        'collaborations' => $collaborations,
        'collaboratorsProgress' => $collaboratorsProgress,
        'courseResults' => $courseResults
    ]);
}


#[Route('company/employee/{id}/edit', name: 'company_employee_edit', methods: ['GET','POST'])]
public function editEmployee(
    User $employee,
    Request $request,
    EntityManagerInterface $em
): Response {
    $company = $this->getUser();

    // Vérifie que l'utilisateur connecté est bien l'entreprise propriétaire
    $isLinked = false;
    foreach ($company->getCollaborationsAsCompany() as $collab) {
        if ($collab->getEmployee()->getId() === $employee->getId()) {
            $isLinked = true;
            break;
        }
    }

    if (!$isLinked) {
        throw $this->createAccessDeniedException('Cet employé ne vous appartient pas.');
    }

    // Crée un formulaire simple basé sur ton type existant
    $form = $this->createForm(PersonalInfoType::class, $employee);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();

        // Si la requête vient d’un appel AJAX (modal ou inline)
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Employé mis à jour avec succès ✅',
                'html' => $this->renderView('company/dashboard/_employee_row.html.twig', [
                    'employee' => $employee,
                ])
            ]);
        }

        $this->addFlash('success', 'Employé mis à jour avec succès ✅');
        return $this->redirectToRoute('company_dashboard');
    }

    // Affichage dans une page ou modal
    return $this->render('company/dashboard/edit_employee.html.twig', [
        'form' => $form->createView(),
        'employee' => $employee,
    ]);
}


}