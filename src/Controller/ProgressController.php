<?php

namespace App\Controller;

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
    QuizAttemptRepository $quizAttemptRepo
): Response {
    $user = $this->getUser();

    if (!$user) {
        return new JsonResponse(['error' => 'Not logged in'], 401);
    }

    // Récupère toutes les inscriptions de l’utilisateur
    $enrollments = $enrollmentRepo->findBy(['user' => $user]);

    $data = [];

    foreach ($enrollments as $enroll) {
        $course = $enroll->getCourse();

        // Progression sur les vidéos de ce cours
        $courseVideos = $course->getVideos();
        $totalVideos = count($courseVideos);

        $progress = $progressRepo->findBy([
            'user' => $user,
        ]);

        // Nombre de vidéos complètes pour ce cours
        $completedVideos = array_filter($progress, function($p) use ($course) {
            return $p->isCompleted() && $p->getVideo()->getCourse() === $course;
        });

        $percent = $totalVideos > 0 ? (count($completedVideos) / $totalVideos * 100) : 0;

        // Dernière tentative de quiz pour cet enrollment
        $attempt = $quizAttemptRepo->findOneBy(
            ['user' => $user, 'enrollment' => $enroll],
            ['createdAt' => 'DESC'] // on prend la dernière tentative
        );

        $quizScore = $attempt ? $attempt->getScore() : null;
        $quizPassed = $attempt ? $attempt->isPassed() : false;

        // Certification si progression 100% et quiz réussi
        $certified = ($percent == 100 && $quizPassed);

        $data[] = [
            'course' => $course,
            'enrollment' => $enroll,
            'progressPercent' => $percent,
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

    return $this->render('progress/transactions.html.twig', [
        'enrollments' => $enrollments,
    ]);
}


}
