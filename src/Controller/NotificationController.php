<?php

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications')]
    public function index(NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        $notifications = 
        $notificationService->getRecentNotifications($user, 50);
        $notificationService->markAllAsRead($user);


        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

#[Route('/notifications/{id}/mark-read', name: 'app_notification_mark_read', methods: ['POST'])]
public function markAsRead(int $id, NotificationService $notificationService): Response
{
    $user = $this->getUser();
    $notification = $notificationService->findByIdAndUser($id, $user);

    if (!$notification) {
        return $this->json(['success' => false, 'error' => 'Not found'], 404);
    }

    if (!$notification->getIsRead()) {
        $notificationService->markAsRead($notification);
    }

    return $this->json(['success' => true]);
}

    #[Route('/notifications/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
public function delete(int $id, NotificationService $notificationService): Response
{
    $user = $this->getUser();
    $notification = $notificationService->findByIdAndUser($id, $user);

    if ($notification) {
        $notificationService->deleteNotification($notification);
        $this->addFlash('success', 'Notification deleted.');
    }

    return $this->redirectToRoute('app_notifications');
}

#[Route('/notifications/delete-all', name: 'app_notifications_delete_all', methods: ['POST'])]
public function deleteAll(NotificationService $notificationService): Response
{
    $user = $this->getUser();
    foreach ($notificationService->getRecentNotifications($user, 1000) as $notif) {
        $notificationService->deleteNotification($notif);
    }

    $this->addFlash('success', 'All notifications deleted.');
    return $this->redirectToRoute('app_notifications');
}

}