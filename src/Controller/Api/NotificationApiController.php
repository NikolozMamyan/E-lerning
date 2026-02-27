<?php

namespace App\Controller\Api;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class NotificationApiController extends AbstractController
{
    #[Route('/notifications', name: 'notifications_list', methods: ['GET'])]
    public function list(Request $request, NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $limit = (int) $request->query->get('limit', 50);
        $limit = max(1, min(200, $limit)); // sécurité

        $notifications = $notificationService->getRecentNotifications($user, $limit);

        // Optionnel : marquer tout comme lu via query param
        $markAll = $request->query->getBoolean('markAllAsRead', false);
        if ($markAll) {
            $notificationService->markAllAsRead($user);
        }

        // DTO simple
        $data = array_map(function ($n) {
            return [
                'id' => $n->getId(),
                'title' => method_exists($n, 'getTitle') ? $n->getTitle() : null,
                'message' => method_exists($n, 'getMessage') ? $n->getMessage() : (method_exists($n, 'getContent') ? $n->getContent() : null),
                'isRead' => (bool) $n->getIsRead(),
                'createdAt' => method_exists($n, 'getCreatedAt') && $n->getCreatedAt()
                    ? $n->getCreatedAt()->format(\DateTimeInterface::ATOM)
                    : null,
                // Ajoute d’autres champs si tu en as (type, url, etc.)
            ];
        }, $notifications);

        return $this->json($data);
    }

    #[Route('/notifications/{id}/read', name: 'notification_mark_read', methods: ['POST'])]
    public function markAsRead(int $id, NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $notification = $notificationService->findByIdAndUser($id, $user);
        if (!$notification) {
            return $this->json(['success' => false, 'error' => 'Not found'], 404);
        }

        if (!$notification->getIsRead()) {
            $notificationService->markAsRead($notification);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/notifications/read-all', name: 'notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $notificationService->markAllAsRead($user);
        return $this->json(['success' => true]);
    }

    #[Route('/notifications/{id}', name: 'notification_delete', methods: ['DELETE'])]
    public function deleteOne(int $id, NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $notification = $notificationService->findByIdAndUser($id, $user);
        if (!$notification) {
            return $this->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $notificationService->deleteNotification($notification);

        return $this->json(['success' => true]);
    }

    #[Route('/notifications', name: 'notifications_delete_all', methods: ['DELETE'])]
    public function deleteAll(NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        // Idéalement: une méthode service "deleteAllForUser" pour éviter de boucler en PHP
        foreach ($notificationService->getRecentNotifications($user, 100000) as $notif) {
            $notificationService->deleteNotification($notif);
        }

        return $this->json(['success' => true]);
    }
}