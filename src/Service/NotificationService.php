<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository
    ) {}

    /**
     * Créer une nouvelle notification
     */
    public function createNotification(
        User $user,
        string $title,
        ?string $message = null,
        string $type = Notification::TYPE_INFO,
        ?string $actionUrl = null,
        ?string $icon = null,
        string $priority = Notification::PRIORITY_NORMAL
    ): Notification {
        $notification = new Notification();
        $notification
            ->setUser($user)
            ->setTitle($title)
            ->setMessage($message)
            ->setType($type)
            ->setActionUrl($actionUrl)
            ->setIcon($icon)
            ->setPriority($priority);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
    

    /**
     * Créer une notification liée à une entité
     */
  public function createEntityNotification(
        User $user,
        string $title,
        object $relatedEntity,
        ?string $message = null,
        string $type = Notification::TYPE_INFO,
        ?string $actionUrl = null,
        ?string $icon = null,
        string $priority = Notification::PRIORITY_NORMAL
    ): Notification {
        $notification = new Notification();
        $notification
            ->setUser($user)
            ->setTitle($title)
            ->setMessage($message)
            ->setType($type)
            ->setActionUrl($actionUrl)
            ->setIcon($icon)
            ->setPriority($priority)
            ->setRelatedEntityId($relatedEntity->getId())
            ->setRelatedEntityType(get_class($relatedEntity));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
        $this->entityManager->flush();
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsReadByUser($user);
    }

    /**
     * Supprimer une notification
     */
    public function deleteNotification(Notification $notification): void
    {
        $this->entityManager->remove($notification);
        $this->entityManager->flush();
    }

    /**
     * Récupérer le nombre de notifications non lues
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    /**
     * Récupérer les notifications récentes
     */
    public function getRecentNotifications(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findRecentByUser($user, $limit);
    }

    /**
     * Nettoyer les anciennes notifications
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return $this->notificationRepository->deleteOldReadNotifications($daysOld);
    }
    

    public function findByIdAndUser(int $id, User $user): ?Notification
{
    return $this->notificationRepository->findOneBy([
        'id' => $id,
        'user' => $user
    ]);
}

}