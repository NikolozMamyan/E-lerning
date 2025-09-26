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

    // Progression par cours
    $progressData = [];
    if ($user) {
        foreach ($courses as $course) {
            $videos = $course->getVideos();
            $completed = 0;
            foreach ($videos as $video) {
                $p = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                if ($p && $p->isCompleted()) {
                    $completed++;
                }
            }
            $percent = count($videos) > 0 ? round(($completed / count($videos)) * 100) : 0;
            $progressData[$course->getId()] = $percent;
        }
    }

    
    // Enrollments (transactions)
    $enrollments = [];
    if ($user) {
        $enrollments = $enrollmentRepo->findByUserWithCourse($user);
    }

    return $this->render('company/dashboard/index.html.twig', [
        'courses' => $courses,
        'progressData' => $progressData,
        'enrollments' => $enrollments,
    ]);
}

}




