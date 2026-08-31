<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JobOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobOffer> */
final class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    /** @return JobOffer[] */
    public function findActive(int $limit = 10): array
    {
        return $this->findBy(['isActive' => true], ['createdAt' => 'DESC'], $limit);
    }

    public function deactivateAllExcept(?JobOffer $jobOffer = null): void
    {
        $queryBuilder = $this->createQueryBuilder('job')
            ->update()
            ->set('job.isActive', ':inactive')
            ->where('job.isActive = :active')
            ->setParameter('inactive', false)
            ->setParameter('active', true);

        if ($jobOffer?->getId() !== null) {
            $queryBuilder
                ->andWhere('job.id != :currentId')
                ->setParameter('currentId', $jobOffer->getId());
        }

        $queryBuilder->getQuery()->execute();
    }
}
