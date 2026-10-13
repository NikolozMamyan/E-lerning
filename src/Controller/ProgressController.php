<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Progress;
use App\Repository\VideoRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\QuizAttemptRepository;
use App\Repository\SubscriptionRepository;
use App\Service\LearningProgressService;
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
// Si le front signale que la vidéo est terminée → on la marque comme complétée
if ($completed && !$progress->isCompleted()) {

    // Durée totale de la vidéo
    $duration = $video->getDuration() ?? 0;

    // On met le watchedTime à la durée totale pour garantir 100%
    if ($duration > 0) {
        $progress->setWatchedSeconds($duration);
    }

    // On marque comme complété
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
public function progress(LearningProgressService $learningProgress): Response
{
    $user = $this->getUser();
    if (!$user) {
        return new JsonResponse(['error' => 'Not logged in'], 401);
    }

    return $this->render('progress/index.html.twig', [
        'coursesData' => $learningProgress->forUser($user),
    ]);
}

#[Route('/app/transactions', name: 'app_transactions')]
public function transactions(
    EnrollmentRepository $enrollmentRepo,
    SubscriptionRepository $subscriptionRepo
): Response {
    $user = $this->getUser();

    if (!$user) {
        throw $this->createAccessDeniedException('Vous devez être connecté.');
    }

    // récupère les enrollments de l’utilisateur avec leurs cours
    $enrollments = $enrollmentRepo->findByUserWithCourse($user);
    $activeSubscription = $subscriptionRepo->findOneBy(
        ['user' => $user, 'isActive' => true],
        ['startDate' => 'DESC']
    );

    if ($activeSubscription && !$activeSubscription->isActive()) {
        $activeSubscription = null;
    }


    return $this->render('progress/transactions.html.twig', [
        'enrollments' => $enrollments,
        'hasSubscription' => $activeSubscription !== null,
        'activeSubscription' => $activeSubscription,
    ]);
}


}
