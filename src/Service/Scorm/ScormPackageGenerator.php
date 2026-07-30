<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Service\Scorm\Exception\InvalidVideoException;
use App\Service\Scorm\Exception\PackageSizeExceededException;
use App\Service\Scorm\Exception\ScormGenerationException;

final class ScormPackageGenerator
{
    private const STATIC_FILES = [
        'index.html' => 'index.html',
        'assets/css/app.css' => 'assets/css/app.css',
        'assets/js/scorm-api.js' => 'assets/js/scorm-api.js',
        'assets/js/course.js' => 'assets/js/course.js',
    ];

    public function __construct(
        private readonly ScormManifestGenerator $manifestGenerator,
        private readonly ScormCourseDataGenerator $courseDataGenerator,
        private readonly string $resourcesDirectory,
        private readonly string $temporaryDirectory,
        private readonly int $maxPackageSize,
        private readonly int $completionThreshold,
        private readonly int $commitIntervalSeconds,
    ) {
        if ($this->maxPackageSize <= 0) {
            throw new \InvalidArgumentException('The maximum SCORM package size must be positive.');
        }
        if ($this->completionThreshold < 1 || $this->completionThreshold > 100) {
            throw new \InvalidArgumentException('The SCORM completion threshold must be between 1 and 100.');
        }
        if ($this->commitIntervalSeconds < 1) {
            throw new \InvalidArgumentException('The SCORM commit interval must be positive.');
        }
    }

    public function generate(
        string $courseIdentifier,
        string $courseTitle,
        array $videos,
        string $outputPath,
    ): string {
        $courseIdentifier = trim($courseIdentifier);
        $courseTitle = trim($courseTitle);

        if ($courseIdentifier === '') {
            throw new ScormGenerationException('The course identifier is required.');
        }
        if ($courseTitle === '') {
            throw new ScormGenerationException('The course title is required.');
        }
        if ($videos === []) {
            throw new ScormGenerationException('The SCORM package must contain at least one video.');
        }
        if (strtolower(pathinfo($outputPath, PATHINFO_EXTENSION)) !== 'zip') {
            throw new ScormGenerationException('The output path must end with .zip.');
        }

        $outputDirectory = dirname($outputPath);
        $this->ensureDirectory($outputDirectory);
        if (file_exists($outputPath)) {
            throw new ScormGenerationException('The output ZIP file already exists.');
        }

        $normalizedVideos = $this->validateAndNormalizeVideos($videos);
        $videoCount = count($normalizedVideos);
        $padding = max(2, strlen((string) $videoCount));
        $packageVideos = [];
        $resourcePaths = array_values(self::STATIC_FILES);

        foreach ($normalizedVideos as $index => &$video) {
            $number = str_pad((string) ($index + 1), $padding, '0', STR_PAD_LEFT);
            $relativePath = 'videos/video-'.$number.'.mp4';
            $video['relativePath'] = $relativePath;
            $packageVideos[] = [
                'id' => 'video-'.$number,
                'title' => $video['title'],
                'src' => $relativePath,
                'position' => $index + 1,
            ];
            $resourcePaths[] = $relativePath;
        }
        unset($video);
        $resourcePaths[] = 'data/course.json';

        $courseJson = $this->courseDataGenerator->generate(
            $courseIdentifier,
            $courseTitle,
            $this->completionThreshold,
            $this->commitIntervalSeconds,
            $packageVideos,
        );
        $manifestXml = $this->manifestGenerator->generate($courseIdentifier, $courseTitle, $resourcePaths);
        $estimatedSize = strlen($courseJson) + strlen($manifestXml);

        foreach (array_keys(self::STATIC_FILES) as $source) {
            $sourcePath = $this->resourcesPath($source);
            if (!is_file($sourcePath) || !is_readable($sourcePath)) {
                throw new ScormGenerationException(sprintf('The SCORM resource "%s" is missing or unreadable.', $source));
            }
            $size = filesize($sourcePath);
            if ($size === false) {
                throw new ScormGenerationException(sprintf('Unable to determine the size of resource "%s".', $source));
            }
            $estimatedSize += $size;
        }
        foreach ($normalizedVideos as $video) {
            $estimatedSize += $video['size'];
        }

        if ($estimatedSize > $this->maxPackageSize) {
            throw new PackageSizeExceededException($estimatedSize, $this->maxPackageSize);
        }

        $this->ensureDirectory($this->temporaryDirectory);
        $workingDirectory = rtrim($this->temporaryDirectory, '\\/').DIRECTORY_SEPARATOR.'package-'.bin2hex(random_bytes(12));
        $temporaryArchive = $outputPath.'.'.bin2hex(random_bytes(8)).'.tmp';

        if (!mkdir($workingDirectory, 0700, true) && !is_dir($workingDirectory)) {
            throw new ScormGenerationException('Unable to create the temporary SCORM directory.');
        }

        try {
            foreach (self::STATIC_FILES as $source => $destination) {
                $this->copyFile($this->resourcesPath($source), $workingDirectory.DIRECTORY_SEPARATOR.$this->filesystemPath($destination));
            }
            $this->writeFile($workingDirectory.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'course.json', $courseJson);
            $this->writeFile($workingDirectory.DIRECTORY_SEPARATOR.'imsmanifest.xml', $manifestXml);

            foreach ($normalizedVideos as $video) {
                $this->copyFile(
                    $video['path'],
                    $workingDirectory.DIRECTORY_SEPARATOR.$this->filesystemPath($video['relativePath']),
                );
            }

            $this->createArchive($workingDirectory, $resourcePaths, $temporaryArchive);
            $archiveSize = filesize($temporaryArchive);
            if ($archiveSize === false) {
                throw new ScormGenerationException('Unable to determine the generated ZIP size.');
            }
            if ($archiveSize > $this->maxPackageSize) {
                throw new PackageSizeExceededException($archiveSize, $this->maxPackageSize);
            }
            if (!rename($temporaryArchive, $outputPath)) {
                throw new ScormGenerationException('Unable to move the ZIP to its final destination.');
            }

            return $outputPath;
        } finally {
            $this->removeDirectory($workingDirectory);
            if (is_file($temporaryArchive)) {
                @unlink($temporaryArchive);
            }
        }
    }

    public function completionThreshold(): int
    {
        return $this->completionThreshold;
    }

    public function maxPackageSize(): int
    {
        return $this->maxPackageSize;
    }

    private function validateAndNormalizeVideos(array $videos): array
    {
        $normalized = [];

        foreach ($videos as $index => $video) {
            $title = isset($video['title']) ? trim((string) $video['title']) : '';
            $path = isset($video['path']) ? trim((string) $video['path']) : '';
            $position = isset($video['position']) ? (int) $video['position'] : $index + 1;

            if ($title === '') {
                throw new InvalidVideoException(sprintf('The title of video %d is required.', $index + 1));
            }
            if ($path === '' || !is_file($path)) {
                throw InvalidVideoException::missing($title);
            }
            if (!is_readable($path)) {
                throw InvalidVideoException::unreadable($title);
            }
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'mp4' || !$this->hasMp4Signature($path)) {
                throw InvalidVideoException::invalidMp4($title);
            }
            $size = filesize($path);
            if ($size === false) {
                throw InvalidVideoException::unreadable($title);
            }

            $normalized[] = [
                'title' => $title,
                'path' => $path,
                'position' => $position,
                'originalIndex' => $index,
                'size' => $size,
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => [$left['position'], $left['originalIndex']] <=> [$right['position'], $right['originalIndex']]);

        return array_map(static function (array $video): array {
            unset($video['originalIndex']);

            return $video;
        }, $normalized);
    }

    private function hasMp4Signature(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 64);

            return is_string($header) && strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp';
        } finally {
            fclose($handle);
        }
    }

    private function createArchive(string $workingDirectory, array $resourcePaths, string $archivePath): void
    {
        $zip = new \ZipArchive();
        $result = $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::EXCL);
        if ($result !== true) {
            throw new ScormGenerationException(sprintf('Unable to create the SCORM ZIP (code %s).', (string) $result));
        }

        try {
            $allPaths = array_merge(['imsmanifest.xml'], $resourcePaths);
            foreach ($allPaths as $relativePath) {
                $absolutePath = $workingDirectory.DIRECTORY_SEPARATOR.$this->filesystemPath($relativePath);
                if (!$zip->addFile($absolutePath, $relativePath)) {
                    throw new ScormGenerationException(sprintf('Unable to add "%s" to the ZIP.', $relativePath));
                }
                if (str_starts_with($relativePath, 'videos/') && !$zip->setCompressionName($relativePath, \ZipArchive::CM_STORE)) {
                    throw new ScormGenerationException(sprintf('Unable to disable compression for "%s".', $relativePath));
                }
            }
        } catch (\Throwable $exception) {
            $zip->close();
            throw $exception;
        }

        if (!$zip->close()) {
            throw new ScormGenerationException('Unable to finalize the SCORM ZIP.');
        }
    }

    private function copyFile(string $source, string $destination): void
    {
        $this->ensureDirectory(dirname($destination));
        if (!copy($source, $destination)) {
            throw new ScormGenerationException(sprintf('Unable to copy the resource "%s".', basename($source)));
        }
    }

    private function writeFile(string $path, string $content): void
    {
        $this->ensureDirectory(dirname($path));
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new ScormGenerationException(sprintf('Unable to write the resource "%s".', basename($path)));
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new ScormGenerationException(sprintf('Unable to create the directory "%s".', basename($directory)));
        }
        if (!is_writable($directory)) {
            throw new ScormGenerationException(sprintf('The directory "%s" is not writable.', basename($directory)));
        }
    }

    private function resourcesPath(string $relativePath): string
    {
        return rtrim($this->resourcesDirectory, '\\/').DIRECTORY_SEPARATOR.$this->filesystemPath($relativePath);
    }

    private function filesystemPath(string $relativePath): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
