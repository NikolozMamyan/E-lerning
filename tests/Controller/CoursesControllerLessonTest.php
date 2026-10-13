<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CoursesController;
use App\Entity\Course;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ProgressRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class CoursesControllerLessonTest extends TestCase
{
    public function testAPlayerCannotOpenALessonFromAnotherCourse(): void
    {
        $course = (new Course())->setTitle('Accessible course');
        $other = (new Course())->setTitle('Another course');
        (new \ReflectionProperty(Course::class, 'id'))->setValue($course, 1);
        (new \ReflectionProperty(Course::class, 'id'))->setValue($other, 2);
        $courses = $this->createMock(CourseRepository::class);
        $courses->method('find')->willReturn($course);
        $videos = $this->createMock(VideoRepository::class);
        $videos->method('find')->willReturn($this->createConfiguredMock(Video::class, ['getCourse' => $other]));
        $user = $this->createConfiguredMock(User::class, ['hasActiveSubscription' => true]);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_EMPLOYEE']));
        $container = new Container();
        $container->set('security.token_storage', $tokens);
        $controller = new CoursesController();
        $controller->setContainer($container);
        $request = Request::create('/app/course/1/22');
        $request->attributes->set('_route', 'app_course_show');
        $this->expectException(NotFoundHttpException::class);
        $controller->show(1, null, 22, $courses, $videos, $this->createMock(QuizAttemptRepository::class), $this->createMock(ProgressRepository::class), $request, $this->createMock(EnrollmentRepository::class), $this->createMock(EntityManagerInterface::class));
    }
}
