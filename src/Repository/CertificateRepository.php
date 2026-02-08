<?php

// src/Repository/CertificateRepository.php

namespace App\Repository;

use App\Entity\Certificate;
use App\Entity\QuizAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificate::class);
    }

    /**
     * @return array<int, array{certificate: Certificate, passedAt: \DateTimeInterface|null}>
     */
    public function findForListWithPassedAt(?\DateTimeImmutable $start, ?\DateTimeImmutable $end): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c AS certificate')
            ->addSelect('MAX(qa.createdAt) AS passedAt')
            ->leftJoin(
                QuizAttempt::class,
                'qa',
                'WITH',
                'qa.user = c.passed AND qa.course = c.course AND qa.passed = true'
            )
            ->groupBy('c.id')
            ->orderBy('passedAt', 'DESC');

        if ($start) {
            $qb->andWhere('qa.createdAt >= :start')
               ->setParameter('start', $start);
        }

        if ($end) {
            $qb->andWhere('qa.createdAt <= :end')
               ->setParameter('end', $end->setTime(23, 59, 59));
        }

        return $qb->getQuery()->getResult();
    }
}

