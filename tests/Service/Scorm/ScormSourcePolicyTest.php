<?php

declare(strict_types=1);

namespace App\Tests\Service\Scorm;

use PHPUnit\Framework\TestCase;

final class ScormSourcePolicyTest extends TestCase
{
    public function testScormSourceFilesContainNoComments(): void
    {
        $projectDirectory = dirname(__DIR__, 3);
        $paths = [
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Service'.DIRECTORY_SEPARATOR.'Scorm',
            $projectDirectory.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'scorm',
            $projectDirectory.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'scorm',
        ];
        $files = [
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Controller'.DIRECTORY_SEPARATOR.'Admin'.DIRECTORY_SEPARATOR.'CourseScormExportController.php',
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Controller'.DIRECTORY_SEPARATOR.'Admin'.DIRECTORY_SEPARATOR.'BilingualScormExportController.php',
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Controller'.DIRECTORY_SEPARATOR.'Admin'.DIRECTORY_SEPARATOR.'ScormGeneratorController.php',
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Controller'.DIRECTORY_SEPARATOR.'Admin'.DIRECTORY_SEPARATOR.'ScormVideoUploadController.php',
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Command'.DIRECTORY_SEPARATOR.'GenerateTestScormPackageCommand.php',
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Command'.DIRECTORY_SEPARATOR.'GenerateBilingualScormPackageCommand.php',
            $projectDirectory.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Enum'.DIRECTORY_SEPARATOR.'ScormLanguage.php',
        ];

        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $files[] = $file->getPathname();
            }
        }

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            self::assertStringNotContainsString('/*', $content, $file);
            self::assertStringNotContainsString('<!--', $content, $file);
            self::assertDoesNotMatchRegularExpression('/^\s*\/\//m', $content, $file);
        }
    }

    public function testPackagedLearnerInterfaceIsEnglish(): void
    {
        $projectDirectory = dirname(__DIR__, 3);
        $index = (string) file_get_contents($projectDirectory.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'scorm'.DIRECTORY_SEPARATOR.'index.html');
        $courseScript = (string) file_get_contents($projectDirectory.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'scorm'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'course.js');

        self::assertStringContainsString('<html lang="en">', $index);
        self::assertStringContainsString('Overall progress', $index);
        self::assertStringContainsString('Previous', $index);
        self::assertStringContainsString('Course completed', $index);
        self::assertStringContainsString('The course data file could not be loaded.', $courseScript);
        self::assertStringNotContainsString('Précédent', $index);
        self::assertStringNotContainsString('Impossible', $courseScript);
    }
}
