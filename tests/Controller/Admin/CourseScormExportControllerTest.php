<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\CourseScormExportController;
use App\Entity\Course;
use App\Entity\Video;
use App\Enum\ScormLanguage;
use App\Service\Scorm\CourseScormExportService;
use App\Service\Scorm\LocalVideoPathResolver;
use App\Service\Scorm\ScormCourseDataGenerator;
use App\Service\Scorm\ScormCourseInspector;
use App\Service\Scorm\ScormManifestGenerator;
use App\Service\Scorm\ScormPackageGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CourseScormExportControllerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-controller-'.bin2hex(random_bytes(8));
        mkdir($this->directory.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads', 0775, true);
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

    public function testReturnsDownloadAndDeletesArchiveAfterSend(): void
    {
        $videoPath = $this->directory.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'lesson.mp4';
        file_put_contents($videoPath, pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom');

        $course = (new Course())->setTitle('Formation Sécurité');
        $video = (new Video())
            ->setTitle('Introduction')
            ->setDuration(10)
            ->setUrl('/uploads/lesson.mp4')
            ->setScormEn('/uploads/lesson.mp4')
            ->setScormTitleEn('Security training');
        $course->addVideo($video);

        $generator = new ScormPackageGenerator(
            new ScormManifestGenerator(),
            new ScormCourseDataGenerator(),
            dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'scorm',
            $this->directory.DIRECTORY_SEPARATOR.'packages',
            10_000_000,
            90,
            10,
        );
        $resolver = new LocalVideoPathResolver($this->directory.DIRECTORY_SEPARATOR.'public');
        $inspector = new ScormCourseInspector($resolver);
        $exporter = new CourseScormExportService(
            $resolver,
            $inspector,
            $generator,
            $this->directory.DIRECTORY_SEPARATOR.'exports',
        );

        $response = (new CourseScormExportController())($course, ScormLanguage::English->value, $exporter, $inspector);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringContainsString('security-training-scorm-en.zip', (string) $response->headers->get('Content-Disposition'));
        $archivePath = $response->getFile()->getPathname();
        self::assertFileExists($archivePath);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archivePath));
        $courseData = json_decode((string) $zip->getFromName('data/course.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Security training', $courseData['title']);
        self::assertSame('Security training', $courseData['videos'][0]['title']);
        self::assertStringContainsString('Security training', (string) $zip->getFromName('imsmanifest.xml'));
        $zip->close();

        ob_start();
        $response->sendContent();
        ob_end_clean();
        self::assertFileDoesNotExist($archivePath);
    }
}
