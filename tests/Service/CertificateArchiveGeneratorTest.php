<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Certificate;
use App\Entity\Course;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Repository\CertificateRepository;
use App\Service\CertificateArchiveGenerator;
use App\Service\CertificatePdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use ZipArchive;

final class CertificateArchiveGeneratorTest extends TestCase
{
    public function testCreatesZipArchiveContainingGeneratedPdf(): void
    {
        $user = (new User())->setUsername('Alice')->setEmail('alice@example.com');
        $course = (new Course())->setTitle('Compliance essentials');
        $attempt = (new QuizAttempt())
            ->setUser($user)
            ->setCourse($course)
            ->setPassed(true);
        $certificate = (new Certificate())
            ->setTitle('Certificate of completion: Compliance essentials')
            ->setRef('CERT-20260902-ARCHIVE')
            ->setCourse($course)
            ->setPassed($user);
        $certificateRepository = $this->createMock(CertificateRepository::class);
        $certificateRepository->method('findOneBy')->willReturn($certificate);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(dirname(__DIR__, 2));
        $generator = new CertificateArchiveGenerator(new CertificatePdfGenerator(
            $certificateRepository,
            $this->createMock(EntityManagerInterface::class),
            $kernel,
        ));

        $archivePath = $generator->generate([$attempt]);

        $archive = new ZipArchive();
        self::assertTrue($archive->open($archivePath));
        $content = $archive->getFromName('certificate_CERT-20260902-ARCHIVE.pdf');
        self::assertIsString($content);
        self::assertStringStartsWith('%PDF-', $content);
        $archive->close();
        unlink($archivePath);
    }
}
