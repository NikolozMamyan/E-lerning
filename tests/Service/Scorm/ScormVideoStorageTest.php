<?php

declare(strict_types=1);

namespace App\Tests\Service\Scorm;

use App\Entity\Course;
use App\Entity\Video;
use App\Enum\ScormLanguage;
use App\Service\Scorm\LocalVideoPathResolver;
use App\Service\Scorm\ScormVideoStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ScormVideoStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-storage-'.bin2hex(random_bytes(8));
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

    public function testStoresLocalizedFileAndTitle(): void
    {
        $source = $this->directory.DIRECTORY_SEPARATOR.'upload.mp4';
        file_put_contents($source, pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom');
        $course = (new Course())->setTitle('Course');
        $video = (new Video())->setTitle('Video')->setDuration(10)->setUrl('base.mp4');
        $course->addVideo($video);
        $this->setId($course, 12);
        $this->setId($video, 34);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $storage = new ScormVideoStorage(
            $entityManager,
            new LocalVideoPathResolver($this->directory),
            $this->directory,
            1_000_000,
        );
        $uploadedFile = new UploadedFile($source, 'training.mp4', 'video/mp4', UPLOAD_ERR_OK, true);

        $storage->save($video, ScormLanguage::German, 'Deutscher Titel', $uploadedFile);

        self::assertSame('Deutscher Titel', $video->getScormTitleDe());
        self::assertNotNull($video->getScormDe());
        self::assertFileExists($this->directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $video->getScormDe()));
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
