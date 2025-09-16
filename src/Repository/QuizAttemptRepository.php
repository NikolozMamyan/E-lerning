<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\QuizAttempt;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    //    /**
    //     * @return QuizAttempt[] Returns an array of QuizAttempt objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('q.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?QuizAttempt
    //    {
    //        return $this->createQueryBuilder('q')
    //            ->andWhere('q.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

        public function findOneByUserId(int $userId): ?QuizAttempt
    {
        return $this->createQueryBuilder('qa')
            ->join('qa.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('qa.createdAt', 'DESC') // pour avoir le + récent
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

            public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.user = :user')
            ->setParameter('user', $user)
            ->orderBy('qa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
