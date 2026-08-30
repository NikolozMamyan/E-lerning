<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommunityEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CommunityEvent> */
final class CommunityEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityEvent::class);
    }

    /** @return CommunityEvent[] */
    public function findUpcoming(int $limit = 3): array
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.isPublished = :published')
            ->andWhere('event.startsAt >= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('event.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
