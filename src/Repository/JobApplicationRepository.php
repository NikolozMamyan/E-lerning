<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JobApplication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobApplication> */
final class JobApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobApplication::class);
    }

    /** @return int[] */
    public function findJobOfferIdsForApplicant(User $applicant): array
    {
        $rows = $this->createQueryBuilder('application')
            ->select('IDENTITY(application.jobOffer) AS jobOfferId')
            ->andWhere('application.applicant = :applicant')
            ->setParameter('applicant', $applicant)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['jobOfferId'], $rows);
    }
}
