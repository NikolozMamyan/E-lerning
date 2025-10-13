<?php

namespace App\Controller\Company;


use App\Service\MailerService;
use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;
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
    EntityManagerInterface $em
): Response {
    $user = $this->getUser();

    // Vérifie que l’utilisateur est bien une entreprise
    if (!$user) {
        throw $this->createAccessDeniedException('You must be logged in as a company.');
    }

    // Tous les cours
    $courses = $courseRepo->findAll();

    // Collaborations (employés)
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

        // Calcule une moyenne globale par collaborateur
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

    // --- Rendu du template ---
    return $this->render('company/dashboard/team_tracking.html.twig', [
        'courses' => $courses,
        'employees' => $employees,
        'collaborations' => $collaborations,
        'collaboratorsProgress' => $collaboratorsProgress,
    ]);
}


}




