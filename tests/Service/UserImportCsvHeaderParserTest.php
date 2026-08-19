<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UserImportCsvHeaderParser;
use PHPUnit\Framework\TestCase;

final class UserImportCsvHeaderParserTest extends TestCase
{
    private const REQUIRED_COLUMNS = ['email', 'username', 'password', 'role'];

    public function testItDetectsCommaSeparatedHeader(): void
    {
        $result = (new UserImportCsvHeaderParser())->parse(
            'email,username,phone,country,city,postalCode,password,role',
            self::REQUIRED_COLUMNS
        );

        self::assertSame(',', $result['delimiter']);
        self::assertSame('email', $result['header'][0]);
        self::assertSame('role', $result['header'][7]);
    }

    public function testItDetectsSemicolonSeparatedHeaderWithUtf8Bom(): void
    {
        $result = (new UserImportCsvHeaderParser())->parse(
            "\xEF\xBB\xBFemail;username;phone;country;city;postalCode;password;role",
            self::REQUIRED_COLUMNS
        );

        self::assertSame(';', $result['delimiter']);
        self::assertSame('email', $result['header'][0]);
        self::assertSame('role', $result['header'][7]);
    }

    public function testQuotedDelimiterDoesNotBreakDetection(): void
    {
        $result = (new UserImportCsvHeaderParser())->parse(
            'email;username;"phone,number";password;role',
            self::REQUIRED_COLUMNS
        );

        self::assertSame(';', $result['delimiter']);
        self::assertSame('phone,number', $result['header'][2]);
    }
}
