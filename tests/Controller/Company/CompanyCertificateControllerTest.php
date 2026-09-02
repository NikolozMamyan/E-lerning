<?php

declare(strict_types=1);

namespace App\Tests\Controller\Company;

use App\Controller\Company\CompanyCertificateController;
use App\Entity\Certificate;
use App\Entity\Course;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Repository\CertificateRepository;
use App\Repository\CourseRepository;
use App\Repository\QuizAttemptRepository;
use App\Service\CertificateArchiveGenerator;
use App\Service\CertificatePdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use ZipArchive;

final class CompanyCertificateControllerTest extends TestCase
{
    public function testIndexAppliesCourseEmailAndFailedFilters(): void
    {
        $company = $this->company();
        $course = (new Course())->setTitle('Compliance essentials');
        $employee = (new User())->setUsername('Alice')->setEmail('alice@example.com');
        $passedAttempt = (new QuizAttempt())
            ->setUser($employee)
            ->setCourse($course)
            ->setPassed(true);
        $failedAttempt = (new QuizAttempt())
            ->setUser($employee)
            ->setCourse($course)
            ->setPassed(false);

        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $quizAttemptRepository->expects(self::exactly(2))
            ->method('findLatestForCompany')
            ->withConsecutive(
                [$company],
                [$company, 9, 'alice@example.com', false],
            )
            ->willReturnOnConsecutiveCalls([$passedAttempt, $failedAttempt], [$failedAttempt]);
        $courseRepository = $this->createMock(CourseRepository::class);
        $courseRepository->expects(self::once())
            ->method('findBy')
            ->with([], ['title' => 'ASC'])
            ->willReturn([$course]);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                'company/certificates/index.html.twig',
                self::callback(static function (array $context) use ($failedAttempt): bool {
                    self::assertSame([$failedAttempt], $context['attempts']);
                    self::assertSame(['courseId' => 9, 'email' => 'alice@example.com', 'status' => 'failed'], $context['filters']);
                    self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $context['stats']);

                    return true;
                }),
            )
            ->willReturn('<html></html>');
        $controller = $this->controller($company, ['twig' => static fn (): Environment => $twig]);
        $request = Request::create(
            '/company/certificates?course=9&email=alice%40example.com&status=failed',
            'GET',
        );

        $response = $controller->index($request, $quizAttemptRepository, $courseRepository);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testDownloadRejectsAttemptOutsideCompany(): void
    {
        $company = $this->company();
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $quizAttemptRepository->expects(self::once())
            ->method('findPassedForCompanyByIds')
            ->with($company, [42])
            ->willReturn([]);
        $generator = new CertificatePdfGenerator(
            $this->createMock(CertificateRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(KernelInterface::class),
        );

        $this->expectException(NotFoundHttpException::class);

        $this->controller($company)->download(42, $quizAttemptRepository, $generator);
    }

    public function testBulkDownloadCreatesZipWithAuthorizedCertificate(): void
    {
        $company = $this->company();
        $employee = (new User())->setUsername('Alice')->setEmail('alice@example.com');
        $course = (new Course())->setTitle('Compliance essentials');
        $attempt = (new QuizAttempt())
            ->setUser($employee)
            ->setCourse($course)
            ->setPassed(true);
        $certificate = (new Certificate())
            ->setTitle('Certificate of completion: Compliance essentials')
            ->setRef('CERT-20260902-ZIP123')
            ->setCourse($course)
            ->setPassed($employee);

        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $quizAttemptRepository->expects(self::once())
            ->method('findPassedForCompanyByIds')
            ->with($company, [12])
            ->willReturn([$attempt]);
        $certificateRepository = $this->createMock(CertificateRepository::class);
        $certificateRepository->method('findOneBy')->willReturn($certificate);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(dirname(__DIR__, 3));
        $generator = new CertificatePdfGenerator(
            $certificateRepository,
            $this->createMock(EntityManagerInterface::class),
            $kernel,
        );
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => $token->getId() === 'company_certificates_bulk'))
            ->willReturn(true);
        $controller = $this->controller($company, [
            'security.csrf.token_manager' => static fn (): CsrfTokenManagerInterface => $csrfTokenManager,
        ]);
        $request = Request::create('/company/certificates/download', 'POST', [
            '_token' => 'valid',
            'attempt_ids' => ['12'],
        ]);

        $response = $controller->bulkDownload(
            $request,
            $quizAttemptRepository,
            new CertificateArchiveGenerator($generator),
        );

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('application/zip', $response->headers->get('Content-Type'));
        $archivePath = $response->getFile()->getPathname();
        $archive = new ZipArchive();
        self::assertTrue($archive->open($archivePath));
        $pdfContent = $archive->getFromName('certificate_CERT-20260902-ZIP123.pdf');
        self::assertIsString($pdfContent);
        self::assertStringStartsWith('%PDF-', $pdfContent);
        $archive->close();
        unlink($archivePath);
    }

    /** @param array<string, callable> $services */
    private function controller(User $company, array $services = []): CompanyCertificateController
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($company, 'main', $company->getRoles()));
        $services['security.token_storage'] = static fn (): TokenStorage => $tokenStorage;

        $controller = new CompanyCertificateController();
        $controller->setContainer(new ServiceLocator($services));

        return $controller;
    }

    private function company(): User
    {
        return (new User())
            ->setUsername('Acme')
            ->setEmail('company@example.com')
            ->setRoles(['ROLE_COMPANY']);
    }
}
