<?php

declare(strict_types=1);

namespace App\Tests\Service\Scorm;

use App\Service\Scorm\Exception\InvalidVideoException;
use App\Service\Scorm\LocalVideoPathResolver;
use PHPUnit\Framework\TestCase;

final class LocalVideoPathResolverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-resolver-'.bin2hex(random_bytes(8));
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

    public function testResolvesUrlRelativeToPublicDirectory(): void
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'lesson.mp4';
        file_put_contents($path, 'test');

        $resolved = (new LocalVideoPathResolver($this->directory))->resolve('/uploads/lesson.mp4?version=1', 'Leçon');

        self::assertSame(realpath($path), $resolved);
    }

    public function testRejectsRemoteUrl(): void
    {
        $this->expectException(InvalidVideoException::class);
        $this->expectExceptionMessage('remote URL');

        (new LocalVideoPathResolver($this->directory))->resolve('https://example.com/video.mp4', 'Leçon');
    }

    public function testRejectsPathOutsidePublicDirectory(): void
    {
        $outside = dirname($this->directory).DIRECTORY_SEPARATOR.'outside-'.bin2hex(random_bytes(4)).'.mp4';
        file_put_contents($outside, 'test');

        try {
            $this->expectException(InvalidVideoException::class);
            $this->expectExceptionMessage('outside the allowed media directory');
            (new LocalVideoPathResolver($this->directory))->resolve($outside, 'Leçon');
        } finally {
            unlink($outside);
        }
    }
}
