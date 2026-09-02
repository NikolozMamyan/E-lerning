<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Certificate;
use App\Entity\Course;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Repository\CertificateRepository;
use App\Service\CertificatePdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CertificatePdfGeneratorTest extends TestCase
{
    public function testGeneratesPdfFromAnExistingCertificate(): void
    {
        $user = (new User())
            ->setUsername('Alice Martin')
            ->setEmail('alice@example.com');
        $course = (new Course())->setTitle('Compliance essentials');
        $attempt = (new QuizAttempt())
            ->setUser($user)
            ->setCourse($course)
            ->setScore(92)
            ->setPassed(true);
        $certificate = (new Certificate())
            ->setTitle('Certificate of completion: Compliance essentials')
            ->setRef('CERT-20260902-ABC123')
            ->setCourse($course)
            ->setPassed($user);

        $certificateRepository = $this->createMock(CertificateRepository::class);
        $certificateRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['passed' => $user, 'course' => $course])
            ->willReturn($certificate);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(dirname(__DIR__, 2));

        $document = (new CertificatePdfGenerator(
            $certificateRepository,
            $entityManager,
            $kernel,
        ))->generate($attempt);

        self::assertStringStartsWith('%PDF-', $document['content']);
        self::assertSame('certificate_CERT-20260902-ABC123.pdf', $document['filename']);
    }

    public function testRejectsFailedAttempt(): void
    {
        $generator = new CertificatePdfGenerator(
            $this->createMock(CertificateRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(KernelInterface::class),
        );

        $this->expectException(\DomainException::class);

        $generator->generate((new QuizAttempt())->setPassed(false));
    }
}
