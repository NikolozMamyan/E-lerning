<?php

namespace App\Controller;


use App\Service\MailerService;
use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\QuizAttemptRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class DashboardController extends AbstractController{


    #[Route('app/dashboard', name: 'app_dashboard')]
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

    // Vidéos récentes
    $recentVideos = [];
    if ($user) {
        $qb = $em->createQueryBuilder()
            ->select('p', 'v', 'c')
            ->from('App\Entity\Progress', 'p')
            ->join('p.video', 'v')
            ->join('v.course', 'c')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(3);

        $recentVideos = $qb->getQuery()->getResult();
    }

    // Quiz attempts
    $userAttempts = $quizAttemptRepo->findByUser($user);

    // Enrollments (transactions)
    $enrollments = [];
    if ($user) {
        $enrollments = $enrollmentRepo->findByUserWithCourse($user);
    }

    return $this->render('dashboard/index.html.twig', [
        'courses' => $courses,
        'progressData' => $progressData,
        'recentVideos' => $recentVideos,
        'userAttempts' => $userAttempts,
        'latestCourses' => $latestCourses,
        'enrollments' => $enrollments,
    ]);
}

}




