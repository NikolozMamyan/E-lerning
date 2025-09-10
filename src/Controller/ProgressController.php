<?php

namespace App\Controller;

use App\Entity\Progress;
use App\Repository\ProgressRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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
}
