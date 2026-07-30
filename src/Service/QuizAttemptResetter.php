<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\QuizAttempt;
use Doctrine\ORM\EntityManagerInterface;

final class QuizAttemptResetter
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function resetFailedAttempt(QuizAttempt $attempt): void
    {
        if ($attempt->isPassed()) {
            throw new \DomainException('Une tentative réussie ne peut pas être réinitialisée.');
        }

        $this->entityManager->remove($attempt);
        $this->entityManager->flush();
    }
}
