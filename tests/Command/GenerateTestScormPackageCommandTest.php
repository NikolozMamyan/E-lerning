<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\GenerateTestScormPackageCommand;
use App\Service\Scorm\ScormCourseDataGenerator;
use App\Service\Scorm\ScormManifestGenerator;
use App\Service\Scorm\ScormPackageGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateTestScormPackageCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-command-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function testCommandGeneratesPackageAndDisplaysSummary(): void
    {
        $video = $this->directory.DIRECTORY_SEPARATOR.'demo.mp4';
        $archive = $this->directory.DIRECTORY_SEPARATOR.'demo.zip';
        file_put_contents($video, pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom');
        $generator = new ScormPackageGenerator(
            new ScormManifestGenerator(),
            new ScormCourseDataGenerator(),
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'scorm',
            $this->directory.DIRECTORY_SEPARATOR.'temporary',
            10_000_000,
            90,
            10,
        );
        $tester = new CommandTester(new GenerateTestScormPackageCommand($generator));

        $status = $tester->execute([
            '--title' => 'Formation test',
            '--identifier' => 'test-001',
            '--video' => [$video],
            '--output' => $archive,
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertFileExists($archive);
        self::assertStringContainsString('SCORM 1.2 package generated', $tester->getDisplay());
        self::assertStringContainsString('90 %', $tester->getDisplay());
    }
}
