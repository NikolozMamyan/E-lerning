<?php

declare(strict_types=1);

namespace App\Tests\Controller\Company;

use App\Controller\Company\CompanyCertificateController;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CompanyCertificateRouteTest extends WebTestCase
{
    public function testCompanyCertificateRoutesRequireAuthentication(): void
    {
        $client = self::createClient();

        foreach ([
            ['GET', '/company/certificates'],
            ['GET', '/company/certificates/999999/download'],
            ['POST', '/company/certificates/download'],
        ] as [$method, $path]) {
            $client->request($method, $path);
            self::assertResponseRedirects('/login');
        }
    }

    public function testControllerRequiresCompanyRole(): void
    {
        $attributes = (new ReflectionClass(CompanyCertificateController::class))->getAttributes(IsGranted::class);

        self::assertCount(1, $attributes);
        self::assertSame('ROLE_COMPANY', $attributes[0]->getArguments()[0]);
    }
}
