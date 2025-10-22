<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Progress;
use App\Repository\VideoRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\QuizAttemptRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProgressController extends AbstractController
{
    #[Route('/api/progress/update/{id}', name: 'app_progress_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        VideoRepository $videoRepo,
        ProgressRepository $progressRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not logged in'], 401);
        }

        $video = $videoRepo->find($id);
        if (!$video) {
            return new JsonResponse(['error' => 'Video not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $watched = isset($data['watched']) ? (int) $data['watched'] : 0;
        $completed = isset($data['completed']) && $data['completed'] === true;

        // Vérifie si un enregistrement existe déjà
        $progress = $progressRepo->findOneBy([
            'user' => $user,
            'video' => $video,
        ]);

        if (!$progress) {
            $progress = new Progress();
            $progress->setUser($user);
            $progress->setVideo($video);
            $progress->setWatchedSeconds(0);
            $progress->setCompleted(false);
        }

        // Toujours garder la valeur max
        $progress->setWatchedSeconds(
            max($progress->getWatchedSeconds(), $watched)
        );

        // Si le front signale que la vidéo est terminée → on la marque comme complétée
        if ($completed && !$progress->isCompleted()) {
            $progress->setCompleted(true);
        }

        $em->persist($progress);
        $em->flush();

        return new JsonResponse([
            'status' => 'ok',
            'completed' => $progress->isCompleted(),
            'watched' => $progress->getWatchedSeconds(),
        ]);
    }
#[Route('/app/progress', name: 'app_progress', methods: ['GET'])]
public function progress(
    Request $request,
    EnrollmentRepository $enrollmentRepo,
    ProgressRepository $progressRepo,
    QuizAttemptRepository $quizAttemptRepo,
    EntityManagerInterface $em
): Response {
    $user = $this->getUser();

    if (!$user) {
        return new JsonResponse(['error' => 'Not logged in'], 401);
    }

    $hasSubscription = $user->hasActiveSubscription();

    // 🔹 Récupération des cours accessibles
    if ($hasSubscription) {
        // Si abonnement → accès à tous les cours
        $courses = $em->getRepository(Course::class)->findAll();
        $enrollments = []; // pas nécessaire dans ce cas
    } else {
        // Sinon → seulement les cours achetés
        $enrollments = $enrollmentRepo->findBy(['user' => $user]);
        $courses = array_map(fn($en) => $en->getCourse(), $enrollments);
    }

    $data = [];

    foreach ($courses as $course) {
        $courseVideos = $course->getVideos();
        $totalVideos = count($courseVideos);

        // Tous les progress de l'utilisateur
        $progressList = $progressRepo->findBy(['user' => $user]);

        $percent = 0;

        if ($totalVideos > 1) {
            $completedVideos = array_filter($progressList, function ($p) use ($course) {
                return $p->isCompleted() && $p->getVideo()->getCourse() === $course;
            });

            $percent = ($totalVideos > 0)
                ? (count($completedVideos) / $totalVideos * 100)
                : 0;
        } elseif ($totalVideos === 1) {
            $video = $courseVideos[0];
            $progress = $progressRepo->findOneBy([
                'user' => $user,
                'video' => $video,
            ]);

            if ($progress) {
                $watched = $progress->getWatchedSeconds();
                $duration = $video->getDuration();

                if ($duration > 0) {
                    $percent = min(($watched / $duration) * 100, 100);
                }
            }
        }

        // 🔹 Récupération du quiz attempt
        if ($hasSubscription) {
            // Les abonnés n'ont pas d'enrollment, donc on cherche par user+course
            $attempt = $quizAttemptRepo->findOneBy(
                ['user' => $user, 'course' => $course],
                ['createdAt' => 'DESC']
            );
            $enrollment = null;
        } else {
            $enroll = array_values(array_filter($enrollments, fn($e) => $e->getCourse() === $course))[0] ?? null;
            $enrollment = $enroll;
            $attempt = $quizAttemptRepo->findOneBy(
                ['user' => $user, 'enrollment' => $enroll],
                ['createdAt' => 'DESC']
            );
        }

        $quizScore = $attempt ? $attempt->getScore() : null;
        $quizPassed = $attempt ? $attempt->isPassed() : false;

        // Certification si progression 100% et quiz réussi
        $certified = $quizPassed;

        $data[] = [
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => round($percent, 2),
            'quizScore' => $quizScore,
            'certified' => $certified,
        ];
    }

    return $this->render('progress/index.html.twig', [
        'coursesData' => $data,
    ]);
}



#[Route('/app/transactions', name: 'app_transactions')]
public function transactions(
    EnrollmentRepository $enrollmentRepo
): Response {
    $user = $this->getUser();

    if (!$user) {
        throw $this->createAccessDeniedException('Vous devez être connecté.');
    }

    // récupère les enrollments de l’utilisateur avec leurs cours
    $enrollments = $enrollmentRepo->findByUserWithCourse($user);
    $subscription = $user->hasActiveSubscription();


    return $this->render('progress/transactions.html.twig', [
        'enrollments' => $enrollments,
        'hasSubscription' => $subscription,
    ]);
}


}
