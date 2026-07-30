<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\QuizAttempt;
use App\Service\QuizAttemptResetter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class QuizAttemptResetterTest extends TestCase
{
    public function testRemovesFailedAttempt(): void
    {
        $attempt = (new QuizAttempt())->setPassed(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('remove')
            ->with($attempt);
        $entityManager->expects(self::once())
            ->method('flush');

        (new QuizAttemptResetter($entityManager))->resetFailedAttempt($attempt);
    }

    public function testRefusesToRemovePassedAttempt(): void
    {
        $attempt = (new QuizAttempt())->setPassed(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Une tentative réussie ne peut pas être réinitialisée.');

        (new QuizAttemptResetter($entityManager))->resetFailedAttempt($attempt);
    }
}
