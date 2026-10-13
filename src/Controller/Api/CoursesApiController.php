<?php

namespace App\Controller\Api;

use App\Entity\Course;
use App\Repository\VideoRepository;
use App\Repository\CourseRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/courses', name: 'api_courses_')]
class CoursesApiController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        CourseRepository $courseRepo,
        ProgressRepository $progressRepo,
        EnrollmentRepository $enrollmentRepo,
        CategoryRepository $categoryRepo
    ): JsonResponse {
        $user = $this->getUser();
        $courses = $courseRepo->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $categories = $categoryRepo->findBy([], ['name' => 'ASC']);

        // Vérifier l'abonnement
        $subscription = $user ? $user->hasActiveSubscription() : false;
                // Préparer les données de cours
        $coursesData = [];
        foreach ($courses as $course) {
            $hasAccess = false;
            
            if ($user) {
                $enrollment = $enrollmentRepo->findOneBy([
                    'user' => $user,
                    'course' => $course,
                ]);
                $hasAccess = $subscription || (bool) $enrollment;
            }

            // Calculer la progression du cours
            $videos = $course->getVideos();
            $totalSeconds = 0;

foreach ($videos as $video) {
    $totalSeconds += (int) $video->getDuration();
}

$minutes = intdiv($totalSeconds, 60);
$seconds = $totalSeconds % 60;

$formattedDuration = sprintf('%02d:%02d', $minutes, $seconds);


            $completedCount = 0;
            
            if ($user && count($videos) > 0) {
                foreach ($videos as $video) {
                    $p = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                    if ($p && $p->isCompleted()) {
                        $completedCount++;
                    }
                }
            }

            $progressPercent = count($videos) > 0 ? round(($completedCount / count($videos)) * 100) : 0;

            // Prix en euros
            $euroPrice = null;
            foreach ($course->getCoursePrices() as $price) {
                if ($price->getCurrency() === 'EUR') {
                    $euroPrice = $price->getPrice() / 100;
                    break;
                }
            }

            $coursesData[] = [
                'id' => $course->getId(),
                'title' => $course->getTitle(),
                'description' => $course->getDescription(),
                'planFormations' =>'/uploads/course_plans/' . $course->getPlanPdf(),
                'image' => $course->getThumb(),
                'createdAt' => $course->getCreatedAt()?->format(DATE_ATOM),
                'firstVideoDuration' => $videos->first() ? $videos->first()->getDuration() : 0,
                'category' => $course->getCategory() ? [
                    'id' => $course->getCategory()->getId(),
                    'name' => $course->getCategory()->getName(),
                    'slug' => $course->getCategory()->getSlug(),
                ] : null,
                'videosCount' => count($videos),
                'courseDuration' => $formattedDuration,
                'updatedAt' => $course->getUpdatedAt()?->format('d/m/Y'),
                'hasAccess' => $hasAccess,
                'progressPercent' => $progressPercent,
                'price' => $euroPrice,
            ];
        }

        // Préparer les catégories
        $categoriesData = [];
        foreach ($categories as $category) {
            $categoriesData[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ];
        }

        return $this->json([
            'courses' => $coursesData,
            'categories' => $categoriesData,
            'hasActiveSubscription' => $subscription,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        int $id,
        Request $request,
        CourseRepository $courseRepo,
        VideoRepository $videoRepo,
        ProgressRepository $progressRepo,
        EnrollmentRepository $enrollmentRepo,
        EntityManagerInterface $em,
        QuizAttemptRepository $attempts
    ): JsonResponse {
        $course = $courseRepo->find($id);
        
        if (!$course) {
            return $this->json(['error' => 'Course not found'], 404);
        }

        $user = $this->getUser();

        // Vérifier l'accès
        $hasAccess = false;
        if ($user) {
            $hasSubscription = $user->hasActiveSubscription();
            $enrollment = $enrollmentRepo->findOneBy([
                'user' => $user,
                'course' => $course,
            ]);
            $hasAccess = $hasSubscription || (bool) $enrollment;
        }

        if (!$hasAccess) {
            // Chercher le prix en euros
            $euroPrice = null;
            foreach ($course->getCoursePrices() as $price) {
                if ($price->getCurrency() === 'EUR') {
                    $euroPrice = $price->getPrice() / 100;
                    break;
                }
            }

            return $this->json([
                'hasAccess' => false,
                'course' => [
                    'id' => $course->getId(),
                    'title' => $course->getTitle(),
                    'description' => $course->getDescription(),
                    'planFormations' =>'/uploads/course_plans/' . $course->getPlanPdf(),
                    'image' => $course->getThumb(),
                    'price' => $euroPrice,
                    'category' => $course->getCategory() ? ['id' => $course->getCategory()->getId(), 'name' => $course->getCategory()->getName(), 'slug' => $course->getCategory()->getSlug()] : null,
                    'updatedAt' => $course->getUpdatedAt()?->format(DATE_ATOM),
                ],
                'videos' => array_map(static fn ($video): array => ['id' => $video->getId(), 'title' => $video->getTitle(), 'duration' => $video->getDuration()], $course->getVideos()->toArray()),
            ]);
        }

        // L'utilisateur a accès
        $videos = $course->getVideos();
        $locale = $request->getLocale();
        $hasFrench = false;
        $hasItalian = false;

        // Préparer les données des vidéos
        $videosData = [];
        $progress = [];
        $updated = false;

        foreach ($videos as $video) {
            // Vérifier si des vidéos FR existent
            if (!empty($video->getUrlFr())) {
                $hasFrench = true;
            }
            if (!empty($video->getUrlIt())) {
                $hasItalian = true;
            }

            // Choisir l'URL selon la locale
            $videoUrl = $video->getUrlForLocale($locale);

            // Progression
            $videoProgress = null;
            if ($user) {
                $p = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                
                if ($p) {
                    $duration = $video->getDuration() ?? 0;

                    // Correction si nécessaire
                    if ($p->isCompleted() && $duration > 0 && $p->getWatchedSeconds() < $duration) {
                        $p->setWatchedSeconds($duration);
                        $updated = true;
                    }

                    $videoProgress = [
                        'completed' => $p->isCompleted(),
                        'watched' => $p->getWatchedSeconds(),
                    ];
                }
            }

            $videosData[] = [
                'id' => $video->getId(),
                'title' => $video->getTitle(),
                'url' => $videoUrl,
                'urlFr' => $video->getUrlFr(),
                'urlIt' => $video->getUrlIt(),
                'duration' => $video->getDuration(),
                // 'position' => $video->getPosition(),
                'progress' => $videoProgress,
            ];
        }

        if ($updated) {
            $em->flush();
        }

        // Calculer la progression globale
        $completedCount = 0;
        foreach ($videosData as $videoData) {
            if ($videoData['progress'] && $videoData['progress']['completed']) {
                $completedCount++;
            }
        }
        $progressPercent = count($videos) > 0 ? round(($completedCount / count($videos)) * 100) : 0;

        return $this->json([
            'hasAccess' => true,
            'course' => [
                'id' => $course->getId(),
                'title' => $course->getTitle(),
                'description' => $course->getDescription(),
                'image' => $course->getThumb(),
                'planFormations' => $course->getPlanPdf() ? '/uploads/course_plans/'.$course->getPlanPdf() : null,
                'updatedAt' => $course->getUpdatedAt()?->format(DATE_ATOM),
                'category' => $course->getCategory() ? [
                    'id' => $course->getCategory()->getId(),
                    'name' => $course->getCategory()->getName(),
                ] : null,
            ],
            'videos' => $videosData,
            'latestQuizAttempt' => ($attempt = $attempts->findLatestForUserAndCourse($user, $course)) ? \App\Service\MobileQuizService::attempt($attempt) : null,
            'hasFrench' => $hasFrench,
            'hasItalian' => $hasItalian,
            'progressPercent' => $progressPercent,
        ]);
    }

    #[Route('/{courseId}/video/{videoId}', name: 'video', methods: ['GET'])]
    public function video(
        int $courseId,
        int $videoId,
        Request $request,
        CourseRepository $courseRepo,
        VideoRepository $videoRepo,
        ProgressRepository $progressRepo,
        EnrollmentRepository $enrollmentRepo
    ): JsonResponse {
        $course = $courseRepo->find($courseId);
        
        if (!$course) {
            return $this->json(['error' => 'Course not found'], 404);
        }

        $video = $videoRepo->find($videoId);
        
        if (!$video || $video->getCourse()->getId() !== $courseId) {
            return $this->json(['error' => 'Video not found'], 404);
        }

        $user = $this->getUser();

        // Vérifier l'accès
        $hasAccess = false;
        if ($user) {
            $hasSubscription = $user->hasActiveSubscription();
            $enrollment = $enrollmentRepo->findOneBy([
                'user' => $user,
                'course' => $course,
            ]);
            $hasAccess = $hasSubscription || (bool) $enrollment;
        }

        if (!$hasAccess) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // Choisir l'URL selon la locale
        $locale = $request->getLocale();
        $videoUrl = $video->getUrlForLocale($locale);

        // Progression
        $videoProgress = null;
        if ($user) {
            $p = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
            if ($p) {
                $videoProgress = [
                    'completed' => $p->isCompleted(),
                    'watched' => $p->getWatchedSeconds(),
                ];
            }
        }

        return $this->json([
            'video' => [
                'id' => $video->getId(),
                'title' => $video->getTitle(),
                'url' => $videoUrl,
                'urlFr' => $video->getUrlFr(),
                'urlIt' => $video->getUrlIt(),
                'duration' => $video->getDuration(),
                // 'position' => $video->getPosition(),
                'progress' => $videoProgress,
            ],
            'course' => [
                'id' => $course->getId(),
                'title' => $course->getTitle(),
            ],
        ]);
    }
}
