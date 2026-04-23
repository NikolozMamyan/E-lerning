<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Enrollment;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    //    /**
    //     * @return Enrollment[] Returns an array of Enrollment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Enrollment
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findByUserWithCourse(User $user)
    {
        return $this->createQueryBuilder('e')
            ->join('e.course', 'c')
            ->addSelect('c')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $userIds
     * @return Enrollment[]
     */
    public function findByUserIdsWithCourse(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->join('e.user', 'u')
            ->join('e.course', 'c')
            ->addSelect('u', 'c')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
