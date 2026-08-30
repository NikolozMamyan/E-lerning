<?php

declare(strict_types=1);

namespace App\Tests\Service\Scorm;

use App\Service\Scorm\Exception\InvalidVideoException;
use App\Service\Scorm\Exception\PackageSizeExceededException;
use App\Service\Scorm\ScormCourseDataGenerator;
use App\Service\Scorm\ScormManifestGenerator;
use App\Service\Scorm\ScormPackageGenerator;
use PHPUnit\Framework\TestCase;

final class ScormPackageGeneratorTest extends TestCase
{
    private string $testDirectory;
    private string $temporaryPackagesDirectory;
    private string $resourcesDirectory;

    protected function setUp(): void
    {
        $this->testDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-test-'.bin2hex(random_bytes(8));
        $this->temporaryPackagesDirectory = $this->testDirectory.DIRECTORY_SEPARATOR.'temporary';
        $this->resourcesDirectory = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'scorm';
        mkdir($this->testDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDirectory);
    }

    public function testGeneratesValidSingleVideoPackageWithoutParentDirectory(): void
    {
        $video = $this->createFakeMp4('introduction.mp4');
        $archive = $this->testDirectory.DIRECTORY_SEPARATOR.'course-scorm.zip';

        $result = $this->generator()->generate('course-123', 'Formation sécurité', [[
            'title' => 'Introduction',
            'path' => $video,
            'filename' => 'unsafe name.mp4',
            'position' => 1,
        ]], $archive);

        self::assertSame($archive, $result);
        self::assertFileExists($archive);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archive));
        $names = $this->zipNames($zip);

        self::assertContains('imsmanifest.xml', $names);
        self::assertContains('index.html', $names);
        self::assertContains('assets/css/app.css', $names);
        self::assertContains('assets/js/scorm-api.js', $names);
        self::assertContains('assets/js/course.js', $names);
        self::assertContains('data/course.json', $names);
        self::assertContains('videos/video-01.mp4', $names);
        self::assertNotContains('course-scorm/imsmanifest.xml', $names);
        self::assertSame(0, $zip->statName('videos/video-01.mp4')['comp_method']);

        $courseData = json_decode((string) $zip->getFromName('data/course.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('course-123', $courseData['identifier']);
        self::assertSame(90, $courseData['completionThreshold']);
        self::assertSame('videos/video-01.mp4', $courseData['videos'][0]['src']);
        $zip->close();
    }

    public function testGeneratesSeveralVideosOrderedByPosition(): void
    {
        $second = $this->createFakeMp4('second.mp4');
        $first = $this->createFakeMp4('first.mp4');
        $archive = $this->testDirectory.DIRECTORY_SEPARATOR.'several.zip';

        $this->generator()->generate('multi', 'Cours multiple', [
            ['title' => 'Deuxième', 'path' => $second, 'position' => 20],
            ['title' => 'Première', 'path' => $first, 'position' => 10],
        ], $archive);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archive));
        self::assertNotFalse($zip->locateName('videos/video-01.mp4'));
        self::assertNotFalse($zip->locateName('videos/video-02.mp4'));
        $courseData = json_decode((string) $zip->getFromName('data/course.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['Première', 'Deuxième'], array_column($courseData['videos'], 'title'));
        self::assertSame([1, 2], array_column($courseData['videos'], 'position'));
        $zip->close();
    }

    public function testRejectsMissingVideo(): void
    {
        $this->expectException(InvalidVideoException::class);
        $this->expectExceptionMessage('could not be found');

        $this->generator()->generate('course', 'Cours', [[
            'title' => 'Absente',
            'path' => $this->testDirectory.DIRECTORY_SEPARATOR.'missing.mp4',
        ]], $this->testDirectory.DIRECTORY_SEPARATOR.'missing.zip');
    }

    public function testRejectsNonMp4Extension(): void
    {
        $path = $this->testDirectory.DIRECTORY_SEPARATOR.'video.txt';
        file_put_contents($path, $this->mp4Bytes());

        $this->expectException(InvalidVideoException::class);
        $this->expectExceptionMessage('valid MP4');

        $this->generator()->generate('course', 'Cours', [[
            'title' => 'Fausse vidéo',
            'path' => $path,
        ]], $this->testDirectory.DIRECTORY_SEPARATOR.'invalid-extension.zip');
    }

    public function testRejectsInvalidMp4Signature(): void
    {
        $path = $this->testDirectory.DIRECTORY_SEPARATOR.'fake.mp4';
        file_put_contents($path, 'not-an-mp4');

        $this->expectException(InvalidVideoException::class);

        $this->generator()->generate('course', 'Cours', [[
            'title' => 'Fausse vidéo',
            'path' => $path,
        ]], $this->testDirectory.DIRECTORY_SEPARATOR.'invalid-signature.zip');
    }

    public function testRejectsPackageAboveConfiguredLimit(): void
    {
        $video = $this->createFakeMp4('large.mp4');

        $this->expectException(PackageSizeExceededException::class);

        $this->generator(100)->generate('course', 'Cours', [[
            'title' => 'Grande vidéo',
            'path' => $video,
        ]], $this->testDirectory.DIRECTORY_SEPARATOR.'large.zip');
    }

    public function testManifestEscapesXmlAndDeclaresEveryResource(): void
    {
        $video = $this->createFakeMp4('xml.mp4');
        $archive = $this->testDirectory.DIRECTORY_SEPARATOR.'xml.zip';

        $this->generator()->generate('course&1', 'Sécurité & <conformité>', [[
            'title' => 'Vidéo & test',
            'path' => $video,
        ]], $archive);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archive));
        $manifest = (string) $zip->getFromName('imsmanifest.xml');
        self::assertStringContainsString('Sécurité &amp; &lt;conformité&gt;', $manifest);
        self::assertStringNotContainsString('Sécurité & <conformité>', $manifest);

        foreach (['index.html', 'assets/css/app.css', 'assets/js/scorm-api.js', 'assets/js/course.js', 'data/course.json', 'videos/video-01.mp4'] as $path) {
            self::assertStringContainsString('href="'.$path.'"', $manifest);
        }
        self::assertStringContainsString('adlcp:scormtype="sco"', $manifest);
        self::assertStringContainsString('<schema>ADL SCORM</schema>', $manifest);
        self::assertStringContainsString('<schemaversion>1.2</schemaversion>', $manifest);
        $zip->close();
    }

    public function testTemporaryDirectoryIsCleanedAfterSuccess(): void
    {
        $video = $this->createFakeMp4('cleanup.mp4');

        $this->generator()->generate('course', 'Cours', [[
            'title' => 'Nettoyage',
            'path' => $video,
        ]], $this->testDirectory.DIRECTORY_SEPARATOR.'cleanup.zip');

        self::assertSame([], glob($this->temporaryPackagesDirectory.DIRECTORY_SEPARATOR.'*') ?: []);
    }

    public function testGeneratesBilingualPackageWithLocalizedQuizzes(): void
    {
        $englishVideo = $this->createFakeMp4('english.mp4');
        $germanVideo = $this->createFakeMp4('german.mp4');
        $archive = $this->testDirectory.DIRECTORY_SEPARATOR.'bilingual.zip';
        $quiz = [
            'title' => 'Knowledge check',
            'questions' => [[
                'id' => 'q1',
                'text' => 'Select the controls',
                'options' => [
                    ['id' => 'A', 'text' => 'Screening'],
                    ['id' => 'B', 'text' => 'Monitoring'],
                ],
                'correct' => ['A', 'B'],
            ]],
        ];

        $this->generator()->generateBilingual('unzer-aml', 'Unzer AML/CFT', [
            'en' => ['label' => 'English', 'title' => 'English training', 'videoPath' => $englishVideo, 'quiz' => $quiz],
            'de' => ['label' => 'Deutsch', 'title' => 'Deutsche Schulung', 'videoPath' => $germanVideo, 'quiz' => $quiz],
        ], $archive, 80);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archive));
        $names = $this->zipNames($zip);
        self::assertContains('videos/video-en.mp4', $names);
        self::assertContains('videos/video-de.mp4', $names);
        self::assertContains('data/quiz-en.json', $names);
        self::assertContains('data/quiz-de.json', $names);
        self::assertContains('assets/js/course-policy.js', $names);
        self::assertContains('assets/js/quiz-engine.js', $names);
        $data = json_decode((string) $zip->getFromName('data/course.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('bilingual-quiz', $data['mode']);
        self::assertSame(80, $data['quizPassThreshold']);
        self::assertSame(['en', 'de'], array_column($data['languages'], 'code'));
        $englishQuiz = json_decode((string) $zip->getFromName('data/quiz-en.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['A', 'B'], $englishQuiz['questions'][0]['correct']);
        $zip->close();
    }

    private function generator(int $maximumSize = 10_000_000): ScormPackageGenerator
    {
        return new ScormPackageGenerator(
            new ScormManifestGenerator(),
            new ScormCourseDataGenerator(),
            $this->resourcesDirectory,
            $this->temporaryPackagesDirectory,
            $maximumSize,
            90,
            10,
        );
    }

    private function createFakeMp4(string $filename): string
    {
        $path = $this->testDirectory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, $this->mp4Bytes());

        return $path;
    }

    private function mp4Bytes(): string
    {
        return pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom';
    }

    private function zipNames(\ZipArchive $zip): array
    {
        $names = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = $zip->getNameIndex($index);
            if ($name !== false) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
