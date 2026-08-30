<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommunityEventRegistration;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CommunityEventRegistration> */
final class CommunityEventRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityEventRegistration::class);
    }

    /** @return int[] */
    public function findEventIdsForMember(User $member): array
    {
        $rows = $this->createQueryBuilder('registration')
            ->select('IDENTITY(registration.communityEvent) AS eventId')
            ->andWhere('registration.member = :member')
            ->setParameter('member', $member)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['eventId'], $rows);
    }
}
