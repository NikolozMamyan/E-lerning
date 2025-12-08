<?php

namespace App\Controller;

use App\Service\MailerService;
use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ArticleRepository; // Ajouter ceci
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
    ArticleRepository $articleRepo, // Ajouter ceci
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
            $totalVideos = count($videos);
            $percent = 0;

            if ($totalVideos > 1) {
                $completed = 0;
                foreach ($videos as $video) {
                    $p = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                    if ($p && $p->isCompleted()) {
                        $completed++;
                    }
                }

                $percent = ($totalVideos > 0)
                    ? round(($completed / $totalVideos) * 100, 2)
                    : 0;
            } elseif ($totalVideos === 1) {
                $video = $videos[0];
                $progress = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);

                if ($progress) {
                    $watched = $progress->getWatchedSeconds();
                    $duration = $video->getDuration();

                    if ($duration > 0) {
                        $percent = round(min(($watched / $duration) * 100, 100), 2);
                    }
                }
            }

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
    $userAttempts = $user ? $quizAttemptRepo->findByUser($user) : [];

    // Enrollments (transactions)
    $enrollments = $user ? $enrollmentRepo->findByUserWithCourse($user) : [];

    // Articles récents (ajouter ceci)
    $latestArticles = $articleRepo->findBy([], ['createdAt' => 'DESC'], 6);

    return $this->render('dashboard/index.html.twig', [
        'courses' => $courses,
        'progressData' => $progressData,
        'recentVideos' => $recentVideos,
        'userAttempts' => $userAttempts,
        'latestCourses' => $latestCourses,
        'enrollments' => $enrollments,
        'latestArticles' => $latestArticles, // Ajouter ceci
    ]);
}
}