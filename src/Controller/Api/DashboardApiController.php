<?php

namespace App\Controller\Api;

use App\Repository\ArticleRepository;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ProgressRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardApiController extends AbstractController
{
    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function dashboard(
        CourseRepository $courseRepo,
        ProgressRepository $progressRepo,
        QuizAttemptRepository $quizAttemptRepo,
        EnrollmentRepository $enrollmentRepo,
        ArticleRepository $articleRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // Tous les cours
        $courses = $courseRepo->findAll();
        $latestCourses = $courseRepo->findBy([], ['updatedAt' => 'DESC'], 3);

        /**
         * Progression par cours (version optimisée: 1 seule requête pour récupérer tous les progress)
         */
        $allVideos = [];
        $courseVideosMap = []; // courseId => [video, video, ...]
        foreach ($courses as $course) {
            $videos = $course->getVideos(); // Doctrine Collection
            $courseVideosMap[$course->getId()] = $videos;

            foreach ($videos as $video) {
                $allVideos[$video->getId()] = $video;
            }
        }

        $progressByVideoId = []; // videoId => Progress
        if (!empty($allVideos)) {
            $qb = $em->createQueryBuilder()
                ->select('p', 'v')
                ->from('App\Entity\Progress', 'p')
                ->join('p.video', 'v')
                ->where('p.user = :user')
                ->andWhere('p.video IN (:videos)')
                ->setParameter('user', $user)
                ->setParameter('videos', array_values($allVideos));

            $allProgress = $qb->getQuery()->getResult();

            foreach ($allProgress as $p) {
                // $p est une entité Progress
                $video = $p->getVideo();
                if ($video) {
                    $progressByVideoId[$video->getId()] = $p;
                }
            }
        }

        $progressData = []; // courseId => percent
        foreach ($courses as $course) {
            $courseId = $course->getId();
            $videos = $courseVideosMap[$courseId] ?? [];
            $totalVideos = is_countable($videos) ? count($videos) : 0;

            $percent = 0.0;

            if ($totalVideos > 1) {
                $completed = 0;

                foreach ($videos as $video) {
                    $p = $progressByVideoId[$video->getId()] ?? null;
                    if ($p && $p->isCompleted()) {
                        $completed++;
                    }
                }

                $percent = $totalVideos > 0 ? round(($completed / $totalVideos) * 100, 2) : 0.0;
            } elseif ($totalVideos === 1) {
                $video = $videos[0];
                $p = $progressByVideoId[$video->getId()] ?? null;

                if ($p) {
                    $watched = (float) $p->getWatchedSeconds();
                    $duration = (float) $video->getDuration();

                    if ($duration > 0) {
                        $percent = round(min(($watched / $duration) * 100, 100), 2);
                    }
                }
            }

            $progressData[$courseId] = $percent;
        }

        /**
         * Vidéos récentes (3 derniers progress de l'utilisateur)
         */
        $qbRecent = $em->createQueryBuilder()
            ->select('p', 'v', 'c')
            ->from('App\Entity\Progress', 'p')
            ->join('p.video', 'v')
            ->join('v.course', 'c')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(3);

        $recentProgress = $qbRecent->getQuery()->getResult();

        $recentVideos = [];
        foreach ($recentProgress as $p) {
            $video = $p->getVideo();
            $course = $video?->getCourse();

            $recentVideos[] = [
                'progressId' => $p->getId(),
                'watchedSeconds' => $p->getWatchedSeconds(),
                'completed' => $p->isCompleted(),
                'video' => $video ? [
                    'id' => $video->getId(),
                    // adapte ces champs selon ton entité Video
                    'title' => method_exists($video, 'getTitle') ? $video->getTitle() : null,
                    'duration' => $video->getDuration(),
                ] : null,
                'course' => $course ? [
                    'id' => $course->getId(),
                    // adapte ces champs selon ton entité Course
                    'title' => method_exists($course, 'getTitle') ? $course->getTitle() : null,
                ] : null,
            ];
        }

        /**
         * Quiz attempts
         * (je ne connais pas tes champs exacts, donc je renvoie un minimum + un exemple)
         */
        $attempts = $quizAttemptRepo->findByUser($user);
        $userAttempts = [];
        foreach ($attempts as $a) {
            $userAttempts[] = [
                'id' => $a->getId(),
                // adapte selon ton entité QuizAttempt
                'score' => method_exists($a, 'getScore') ? $a->getScore() : null,
                'createdAt' => method_exists($a, 'getCreatedAt') && $a->getCreatedAt()
                    ? $a->getCreatedAt()->format(DATE_ATOM)
                    : null,
            ];
        }

        /**
         * Enrollments (transactions)
         */
        $enrollmentsEntities = $enrollmentRepo->findByUserWithCourse($user);
        $enrollments = [];
        foreach ($enrollmentsEntities as $e) {
            $course = method_exists($e, 'getCourse') ? $e->getCourse() : null;

            $enrollments[] = [
                'id' => $e->getId(),
                'createdAt' => method_exists($e, 'getCreatedAt') && $e->getCreatedAt()
                    ? $e->getCreatedAt()->format(DATE_ATOM)
                    : null,
                'course' => $course ? [
                    'id' => $course->getId(),
                    'title' => method_exists($course, 'getTitle') ? $course->getTitle() : null,
                ] : null,
            ];
        }

        /**
         * Articles récents
         */
        $latestArticlesEntities = $articleRepo->findBy([], ['createdAt' => 'DESC'], 6);
        $latestArticles = [];
        foreach ($latestArticlesEntities as $article) {
            $latestArticles[] = [
                'id' => $article->getId(),
                'image'=> method_exists($article, 'getImage') ? $article->getImage() : null,
                'title' => method_exists($article, 'getTitre') ? $article->getTitre() : null,
                'createdAt' => method_exists($article, 'getCreatedAt') && $article->getCreatedAt()
                    ? $article->getCreatedAt()->format(DATE_ATOM)
                    : null,
                // ajoute ici: image, likes, commentsCount, etc. quand tu les exposes
            ];
        }

        /**
         * Serialization simple pour courses / latestCourses
         * (adapte les champs selon tes entités Course)
         */
        $coursesOut = [];
        foreach ($courses as $course) {
            $coursesOut[] = [
                'id' => $course->getId(),
                'title' => method_exists($course, 'getTitle') ? $course->getTitle() : null,
                'updatedAt' => method_exists($course, 'getUpdatedAt') && $course->getUpdatedAt()
                    ? $course->getUpdatedAt()->format(DATE_ATOM)
                    : null,
            ];
        }

        $latestCoursesOut = [];
        foreach ($latestCourses as $course) {
            $latestCoursesOut[] = [
                'id' => $course->getId(),
                'title' => method_exists($course, 'getTitle') ? $course->getTitle() : null,
                'updatedAt' => method_exists($course, 'getUpdatedAt') && $course->getUpdatedAt()
                    ? $course->getUpdatedAt()->format(DATE_ATOM)
                    : null,
            ];
        }

        return new JsonResponse([
            'courses' => $coursesOut,
            'latestCourses' => $latestCoursesOut,
            'progressData' => $progressData,
            'recentVideos' => $recentVideos,
            'userAttempts' => $userAttempts,
            'enrollments' => $enrollments,
            'latestArticles' => $latestArticles,
        ]);
    }
}
