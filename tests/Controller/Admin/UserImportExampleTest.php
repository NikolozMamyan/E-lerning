<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\AdminController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserImportExampleTest extends TestCase
{
    public function testExampleIsAUtf8SemicolonSeparatedCsv(): void
    {
        $response = (new AdminController())->downloadUserImportExample();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString(
            'attachment; filename="modele_import_utilisateurs.csv"',
            (string) $response->headers->get('Content-Disposition')
        );
        self::assertStringStartsWith(
            "\xEF\xBB\xBFemail;username;phone;country;city;postalCode;password;role\r\n",
            (string) $response->getContent()
        );
    }
}
