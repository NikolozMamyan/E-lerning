<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\User;
use App\Entity\Certificate;
use App\Entity\QuizAttempt;
use Doctrine\ORM\QueryBuilder;
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
     * @return QuizAttempt[]
     */
    public function findPassedByUser(User $user, int $limit = 4): array
    {
        return $this->createQueryBuilder('qa')
            ->addSelect('course', 'category')
            ->leftJoin('qa.course', 'course')
            ->leftJoin('course.category', 'category')
            ->andWhere('qa.user = :user')
            ->andWhere('qa.passed = true')
            ->andWhere('qa.id IN (
                SELECT MAX(qa2.id)
                FROM App\Entity\QuizAttempt qa2
                JOIN qa2.course course2
                WHERE qa2.user = :user AND qa2.passed = true
                GROUP BY course2.id
            )')
            ->setParameter('user', $user)
            ->orderBy('qa.createdAt', 'DESC')
            ->addOrderBy('qa.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPassedCoursesByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('qa')
            ->select('COUNT(DISTINCT course.id)')
            ->join('qa.course', 'course')
            ->andWhere('qa.user = :user')
            ->andWhere('qa.passed = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
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

/**
 * Returns one current certification result per employee and course.
 *
 * @return QuizAttempt[]
 */
public function findLatestForCompany(
    User $company,
    ?int $courseId = null,
    string $employeeEmail = '',
    ?bool $passed = null,
): array {
    $qb = $this->createQueryBuilder('qa')
        ->distinct()
        ->addSelect('u', 'c')
        ->join('qa.user', 'u')
        ->join('qa.course', 'c')
        ->join('u.collaborationsAsEmployee', 'collaboration')
        ->andWhere('collaboration.company = :company')
        ->andWhere('qa.id IN (
            SELECT MAX(qa2.id)
            FROM App\Entity\QuizAttempt qa2
            JOIN qa2.user u2
            JOIN qa2.course c2
            JOIN u2.collaborationsAsEmployee collaboration2
            WHERE collaboration2.company = :company
            GROUP BY u2.id, c2.id
        )')
        ->setParameter('company', $company)
        ->orderBy('qa.createdAt', 'DESC')
        ->addOrderBy('qa.id', 'DESC');

    if ($courseId !== null) {
        $qb->andWhere('c.id = :courseId')
            ->setParameter('courseId', $courseId);
    }

    if ($employeeEmail !== '') {
        $qb->andWhere('LOWER(u.email) LIKE LOWER(:employeeEmail)')
            ->setParameter('employeeEmail', '%'.$employeeEmail.'%');
    }

    if ($passed !== null) {
        $qb->andWhere('qa.passed = :passed')
            ->setParameter('passed', $passed);
    }

    return $qb->getQuery()->getResult();
}

/**
 * @param int[] $attemptIds
 *
 * @return QuizAttempt[]
 */
public function findPassedForCompanyByIds(User $company, array $attemptIds): array
{
    if ($attemptIds === []) {
        return [];
    }

    return $this->createQueryBuilder('qa')
        ->distinct()
        ->addSelect('u', 'c')
        ->join('qa.user', 'u')
        ->join('qa.course', 'c')
        ->join('u.collaborationsAsEmployee', 'collaboration')
        ->andWhere('collaboration.company = :company')
        ->andWhere('qa.id IN (:attemptIds)')
        ->andWhere('qa.passed = true')
        ->setParameter('company', $company)
        ->setParameter('attemptIds', $attemptIds)
        ->orderBy('u.username', 'ASC')
        ->addOrderBy('c.title', 'ASC')
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

/**
 * @return QuizAttempt[]
 */
public function findFailedForAdmin(
    string $search = '',
    ?int $courseId = null,
    ?\DateTimeImmutable $dateFrom = null,
    ?\DateTimeImmutable $dateTo = null,
    string $sort = 'newest',
    int $limit = 25,
    int $offset = 0,
): array
{
    $qb = $this->createFailedAdminQueryBuilder($search, $courseId, $dateFrom, $dateTo)
        ->addSelect('u', 'c')
        ->setMaxResults($limit)
        ->setFirstResult($offset);

    match ($sort) {
        'oldest' => $qb
            ->orderBy('qa.createdAt', 'ASC')
            ->addOrderBy('qa.id', 'ASC'),
        'score_asc' => $qb
            ->orderBy('qa.score', 'ASC')
            ->addOrderBy('qa.createdAt', 'DESC')
            ->addOrderBy('qa.id', 'DESC'),
        'score_desc' => $qb
            ->orderBy('qa.score', 'DESC')
            ->addOrderBy('qa.createdAt', 'DESC')
            ->addOrderBy('qa.id', 'DESC'),
        default => $qb
            ->orderBy('qa.createdAt', 'DESC')
            ->addOrderBy('qa.id', 'DESC'),
    };

    return $qb->getQuery()->getResult();
}

public function countFailedForAdmin(
    string $search = '',
    ?int $courseId = null,
    ?\DateTimeImmutable $dateFrom = null,
    ?\DateTimeImmutable $dateTo = null,
): int
{
    return (int) $this->createFailedAdminQueryBuilder($search, $courseId, $dateFrom, $dateTo)
        ->select('COUNT(qa.id)')
        ->getQuery()
        ->getSingleScalarResult();
}

private function createFailedAdminQueryBuilder(
    string $search,
    ?int $courseId,
    ?\DateTimeImmutable $dateFrom,
    ?\DateTimeImmutable $dateTo,
): QueryBuilder
{
    $qb = $this->createQueryBuilder('qa')
        ->leftJoin('qa.user', 'u')
        ->leftJoin('qa.course', 'c')
        ->andWhere('qa.passed = :passed')
        ->setParameter('passed', false);

    if ($search !== '') {
        $qb->andWhere(
            $qb->expr()->orX(
                'LOWER(u.username) LIKE LOWER(:search)',
                'LOWER(u.email) LIKE LOWER(:search)',
                'LOWER(c.title) LIKE LOWER(:search)',
            ),
        )
            ->setParameter('search', '%'.$search.'%');
    }

    if ($courseId !== null) {
        $qb->andWhere('c.id = :courseId')
            ->setParameter('courseId', $courseId);
    }

    if ($dateFrom !== null) {
        $qb->andWhere('qa.createdAt >= :dateFrom')
            ->setParameter('dateFrom', $dateFrom->setTime(0, 0));
    }

    if ($dateTo !== null) {
        $qb->andWhere('qa.createdAt <= :dateTo')
            ->setParameter('dateTo', $dateTo->setTime(23, 59, 59));
    }

    return $qb;
}

/**
 * @return array<int, array{attempt: QuizAttempt, failedAt: \DateTimeInterface}>
 */
public function findFailedForCertificateList(?\DateTimeImmutable $start, ?\DateTimeImmutable $end, ?int $courseId = null): array
{
    $qb = $this->createQueryBuilder('qa')
        ->select('qa AS attempt')
        ->addSelect('qa.createdAt AS failedAt')
        ->leftJoin(
            Certificate::class,
            'cert',
            'WITH',
            'cert.passed = qa.user AND cert.course = qa.course'
        )
        ->leftJoin(
            QuizAttempt::class,
            'passedQa',
            'WITH',
            'passedQa.user = qa.user AND passedQa.course = qa.course AND passedQa.passed = true'
        )
        ->andWhere('qa.passed = false')
        ->andWhere('cert.id IS NULL')
        ->andWhere('passedQa.id IS NULL')
        ->orderBy('qa.createdAt', 'DESC')
        ->addOrderBy('qa.id', 'DESC');

    if ($start) {
        $qb->andWhere('qa.createdAt >= :start')
            ->setParameter('start', $start);
    }

    if ($end) {
        $qb->andWhere('qa.createdAt <= :end')
            ->setParameter('end', $end->setTime(23, 59, 59));
    }

    if ($courseId) {
        $qb->andWhere('IDENTITY(qa.course) = :courseId')
            ->setParameter('courseId', $courseId);
    }

    return $qb->getQuery()->getResult();
}
}
