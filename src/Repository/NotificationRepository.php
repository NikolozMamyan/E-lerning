<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Récupère les notifications non lues d'un utilisateur
     */
    public function findUnreadByUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les dernières notifications d'un utilisateur (limitées)
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues d'un utilisateur
     */
    public function countUnreadByUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('count(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsReadByUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', true)
            ->set('n.readAt', ':now')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les anciennes notifications lues (cleanup)
     */
    public function deleteOldReadNotifications(int $daysOld = 30): int
    {
        $date = new \DateTime();
        $date->modify("-{$daysOld} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.isRead = true')
            ->andWhere('n.readAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les notifications par type
     */
    public function findByUserAndType(User $user, string $type): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}