<?php

declare(strict_types=1);

namespace App\Tests\Service\Scorm;

use App\Entity\Course;
use App\Entity\Video;
use App\Service\Scorm\Exception\ScormGenerationException;
use App\Service\Scorm\LocalVideoPathResolver;
use App\Service\Scorm\ScormCourseInspector;
use PHPUnit\Framework\TestCase;

final class ScormCourseInspectorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-inspector-'.bin2hex(random_bytes(8));
        mkdir($this->directory.DIRECTORY_SEPARATOR.'uploads', 0775, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function testReportsEachLocalizedVersionIndependently(): void
    {
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'english.mp4', pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom');
        $course = (new Course())->setTitle('Base course');
        $course->addVideo(
            (new Video())
                ->setTitle('Base video')
                ->setDuration(10)
                ->setUrl('https://example.com/base.mp4')
                ->setScormEn('/uploads/english.mp4')
                ->setScormTitleEn('English title')
                ->setScormTitleFr('Titre français'),
        );
        $inspector = new ScormCourseInspector(new LocalVideoPathResolver($this->directory));

        $statuses = $inspector->languageStatuses($course);

        self::assertTrue($statuses[0]['ready']);
        self::assertSame('English title', $statuses[0]['title']);
        self::assertFalse($statuses[1]['ready']);
        self::assertFalse($statuses[2]['ready']);
    }

    public function testRejectsCourseWithoutExactlyOneVideo(): void
    {
        $this->expectException(ScormGenerationException::class);
        $this->expectExceptionMessage('exactly one video');

        $course = (new Course())->setTitle('Invalid course');
        foreach (['First', 'Second'] as $title) {
            $course->addVideo((new Video())->setTitle($title)->setDuration(1)->setUrl('local.mp4'));
        }

        (new ScormCourseInspector(new LocalVideoPathResolver($this->directory)))->singleVideo($course);
    }
}
