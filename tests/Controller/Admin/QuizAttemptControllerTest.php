<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\QuizAttemptController;
use App\Repository\CourseRepository;
use App\Repository\QuizAttemptRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class QuizAttemptControllerTest extends TestCase
{
    public function testEmptyOptionalFiltersDoNotCauseBadRequest(): void
    {
        $request = Request::create(
            '/admin/quizzes?q=alice&course=&date_from=&date_to=&sort=newest&page=',
            'GET',
        );
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $quizAttemptRepository->expects(self::once())
            ->method('countFailedForAdmin')
            ->with('alice', null, null, null)
            ->willReturn(0);
        $quizAttemptRepository->expects(self::once())
            ->method('findFailedForAdmin')
            ->with('alice', null, null, null, 'newest', 25, 0)
            ->willReturn([]);
        $courseRepository = $this->createMock(CourseRepository::class);
        $courseRepository->expects(self::once())
            ->method('findBy')
            ->with([], ['title' => 'ASC'])
            ->willReturn([]);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/quiz_attempt/index.html.twig', self::isType('array'))
            ->willReturn('<html></html>');

        $controller = new QuizAttemptController();
        $controller->setContainer(new ServiceLocator([
            'twig' => static fn (): Environment => $twig,
        ]));

        $response = $controller($request, $quizAttemptRepository, $courseRepository);

        self::assertSame(200, $response->getStatusCode());
    }
}
