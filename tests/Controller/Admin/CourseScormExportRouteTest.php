<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CourseScormExportRouteTest extends WebTestCase
{
    public function testScormAdminRoutesRequireAuthentication(): void
    {
        $client = self::createClient();
        foreach ([
            ['GET', '/admin/scorm'],
            ['GET', '/admin/scorm/courses/999999/export/en'],
            ['POST', '/admin/scorm/videos/999999/upload'],
        ] as [$method, $path]) {
            $client->request($method, $path);
            self::assertResponseRedirects('/login');
        }
    }
}
