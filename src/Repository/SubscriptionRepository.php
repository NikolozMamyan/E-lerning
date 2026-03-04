<?php
// src/Repository/SubscriptionRepository.php
namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * @return int[] user ids qui ont au moins 1 abonnement actif à $at
     */
    public function findUserIdsWithActiveSubscription(array $userIds, ?\DateTimeInterface $at = null): array
    {
        if (!$userIds) {
            return [];
        }

        $at ??= new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT IDENTITY(s.user) AS userId')
            ->andWhere('IDENTITY(s.user) IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->andWhere('s.startDate <= :at')
            ->setParameter('at', $at)
            ->andWhere('s.endDate > :at');

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(static fn($r) => (int) $r['userId'], $rows);
    }

    public function userHasActiveSubscription(int $userId, ?\DateTimeInterface $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('IDENTITY(s.user) = :userId')
            ->setParameter('userId', $userId)
            ->andWhere('s.isActive = true')
            ->andWhere('s.startDate <= :at')
            ->andWhere('s.endDate > :at')
            ->setParameter('at', $at)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}