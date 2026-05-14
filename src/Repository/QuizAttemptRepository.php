<?php

namespace App\Repository;

use App\Entity\Course;
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
/**
 * @param int[] $userIds
 * @return array<int, QuizAttempt>  // [userId => QuizAttempt]
 */
public function findLatestByUserIds(array $userIds): array
{
    if (!$userIds) return [];

    $attempts = $this->createQueryBuilder('qa')
        ->join('qa.user', 'u')
        ->andWhere('u.id IN (:userIds)')
        ->andWhere('qa.id = (
            SELECT MAX(qa2.id)
            FROM App\Entity\QuizAttempt qa2
            WHERE qa2.user = qa.user
        )')
        ->setParameter('userIds', $userIds)
        ->getQuery()
        ->getResult();

    $byUserId = [];
    foreach ($attempts as $attempt) {
        $byUserId[$attempt->getUser()->getId()] = $attempt;
    }
    return $byUserId;
}

/**
 * @param int[] $userIds
 * @return QuizAttempt[]
 */
public function findLatestByUserIdsGroupedByCourse(array $userIds): array
{
    if (!$userIds) {
        return [];
    }

    return $this->createQueryBuilder('qa')
        ->join('qa.user', 'u')
        ->andWhere('u.id IN (:userIds)')
        ->andWhere('qa.id IN (
            SELECT MAX(qa2.id)
            FROM App\Entity\QuizAttempt qa2
            JOIN qa2.user u2
            JOIN qa2.course c2
            WHERE u2.id IN (:userIds)
            GROUP BY u2.id, c2.id
        )')
        ->setParameter('userIds', $userIds)
        ->getQuery()
        ->getResult();
}

/**
 * @param int[] $userIds
 * @return QuizAttempt[]
 */
public function findByUserIds(array $userIds): array
{
    if (!$userIds) {
        return [];
    }

    return $this->createQueryBuilder('qa')
        ->join('qa.user', 'u')
        ->leftJoin('qa.course', 'c')
        ->addSelect('u', 'c')
        ->andWhere('u.id IN (:userIds)')
        ->setParameter('userIds', $userIds)
        ->orderBy('qa.createdAt', 'DESC')
        ->addOrderBy('qa.id', 'DESC')
        ->getQuery()
        ->getResult();
}

public function findLatestForUserAndCourse(User $user, Course $course): ?QuizAttempt
{
    return $this->createQueryBuilder('qa')
        ->andWhere('qa.user = :user')
        ->andWhere('qa.course = :course')
        ->setParameter('user', $user)
        ->setParameter('course', $course)
        ->orderBy('qa.createdAt', 'DESC')
        ->addOrderBy('qa.id', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}

public function findLatestPassedForUserAndCourse(User $user, Course $course): ?QuizAttempt
{
    return $this->createQueryBuilder('qa')
        ->andWhere('qa.user = :user')
        ->andWhere('qa.course = :course')
        ->andWhere('qa.passed = true')
        ->setParameter('user', $user)
        ->setParameter('course', $course)
        ->orderBy('qa.createdAt', 'DESC')
        ->addOrderBy('qa.id', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}

public function hasPassedAttempt(User $user, Course $course): bool
{
    return null !== $this->createQueryBuilder('qa')
        ->select('qa.id')
        ->andWhere('qa.user = :user')
        ->andWhere('qa.course = :course')
        ->andWhere('qa.passed = true')
        ->setParameter('user', $user)
        ->setParameter('course', $course)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
}
