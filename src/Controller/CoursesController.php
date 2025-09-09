<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\VideoRepository;
use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CoursesController extends AbstractController
{
#[Route('app/courses', name: 'app_courses')]
public function index(
    CourseRepository $courseRepo,
    ProgressRepository $progressRepo
): Response {
    $user = $this->getUser();
    $courses = $courseRepo->findAll();

    $progress = [];
    if ($user) {
        $userProgress = $progressRepo->findBy(['user' => $user]);
        foreach ($userProgress as $p) {
            $progress[$p->getVideo()->getId()] = $p;
        }
    }

    return $this->render('courses/index.html.twig', [
        'courses' => $courses,
        'progress' => $progress,
    ]);
}



#[Route('app/course/{id}/{videoId?}', name: 'app_course_show', requirements: ['videoId' => '\d+'])]
public function show(
    int $id,
    ?int $videoId,
    CourseRepository $courseRepo,
    VideoRepository $videoRepo,
    ProgressRepository $progressRepo,
    EnrollmentRepository $enrollmentRepo
): Response {
    $course = $courseRepo->find($id);
    if (!$course) {
        throw $this->createNotFoundException('Course not found');
    }

    $user = $this->getUser();

    // 🔒 Vérifier si l’utilisateur a accès au cours
    $hasAccess = false;
    if ($user) {
        $enrollment = $enrollmentRepo->findOneBy([
            'user' => $user,
            'course' => $course
        ]);
        $hasAccess = (bool) $enrollment;
    }

    if (!$hasAccess) {
        // utilisateur non abonné → renvoi vers une page "lock"
        return $this->render('courses/locked.html.twig', [
            'course' => $course
        ]);
    }

    // ✅ Si l’utilisateur a accès, on affiche les vidéos
    $videos = $course->getVideos();

    // vidéo courante
    $currentVideo = null;
    if ($videoId) {
        $currentVideo = $videoRepo->find($videoId);
    }
    if (!$currentVideo && count($videos) > 0) {
        $currentVideo = $videos[0];
    }

    // progression
    $progress = [];
    if ($user) {
        foreach ($videos as $v) {
            $p = $progressRepo->findOneBy(['user' => $user, 'video' => $v]);
            if ($p) {
                $progress[$v->getId()] = [
                    'completed' => $p->isCompleted(),
                    'watched' => $p->getWatchedSeconds()
                ];
            }
        }
    }

    $completedCount = count(array_filter($progress, fn($p) => $p['completed'] ?? false));
    $progressPercent = count($videos) > 0 ? round(($completedCount / count($videos)) * 100) : 0;

    return $this->render('courses/show.html.twig', [
        'course' => $course,
        'videos' => $videos,
        'currentVideo' => $currentVideo,
        'progress' => $progress,
        'progressPercent' => $progressPercent,
    ]);
}


}
