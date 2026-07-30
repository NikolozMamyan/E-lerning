<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class QuizAttemptRouteTest extends WebTestCase
{
    public function testQuizAttemptAdminRoutesRequireAuthentication(): void
    {
        $client = self::createClient();

        foreach ([
            ['GET', '/admin/quizzes'],
            ['POST', '/admin/quizzes/999999/reset'],
        ] as [$method, $path]) {
            $client->request($method, $path);
            self::assertResponseRedirects('/login');
        }
    }
}
