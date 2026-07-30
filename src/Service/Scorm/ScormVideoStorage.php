<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Entity\Video;
use App\Enum\ScormLanguage;
use App\Service\Scorm\Exception\InvalidVideoException;
use App\Service\Scorm\Exception\ScormGenerationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ScormVideoStorage
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocalVideoPathResolver $videoPathResolver,
        private readonly string $publicDirectory,
        private readonly int $maxFileSize,
    ) {
    }

    public function save(Video $video, ScormLanguage $language, string $title, ?UploadedFile $uploadedFile): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new ScormGenerationException('The localized title is required.');
        }
        if (mb_strlen($title) > 255) {
            throw new ScormGenerationException('The localized title cannot exceed 255 characters.');
        }

        $oldFile = $language->file($video);
        $oldTitle = $language->title($video);
        $hasExistingFile = $this->storedFileExists($oldFile, $title);

        if (!$uploadedFile instanceof UploadedFile && !$hasExistingFile) {
            throw new ScormGenerationException('Select an MP4 file before saving this language.');
        }

        $newRelativePath = $oldFile;
        $newAbsolutePath = null;

        if ($uploadedFile instanceof UploadedFile) {
            $this->validateUpload($uploadedFile, $title);
            $courseId = $video->getCourse()?->getId();
            $videoId = $video->getId();

            if ($courseId === null || $videoId === null) {
                throw new ScormGenerationException('The course and video must be saved before uploading a SCORM file.');
            }

            $relativeDirectory = sprintf('uploads/scorm/%d/%d', $courseId, $videoId);
            $absoluteDirectory = rtrim($this->publicDirectory, '\\/').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
            if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
                throw new ScormGenerationException('Unable to create the SCORM upload directory.');
            }

            $filename = sprintf('video-%s-%s.mp4', $language->value, bin2hex(random_bytes(12)));

            try {
                $movedFile = $uploadedFile->move($absoluteDirectory, $filename);
            } catch (\Throwable $exception) {
                throw new ScormGenerationException('Unable to store the uploaded MP4 file.', previous: $exception);
            }

            $newAbsolutePath = $movedFile->getPathname();
            $newRelativePath = $relativeDirectory.'/'.$filename;
        }

        $language->assign($video, $newRelativePath, $title);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $language->assign($video, $oldFile, $oldTitle);
            if ($newAbsolutePath !== null && is_file($newAbsolutePath)) {
                @unlink($newAbsolutePath);
            }

            throw new ScormGenerationException('Unable to save the SCORM video information.', previous: $exception);
        }

        if ($newAbsolutePath !== null && $oldFile !== null && $oldFile !== $newRelativePath) {
            $this->deleteOldFile($oldFile);
        }
    }

    private function validateUpload(UploadedFile $uploadedFile, string $title): void
    {
        if (!$uploadedFile->isValid()) {
            throw new ScormGenerationException('The MP4 upload failed.');
        }
        if (strtolower($uploadedFile->getClientOriginalExtension()) !== 'mp4') {
            throw InvalidVideoException::invalidMp4($title);
        }

        $size = $uploadedFile->getSize();
        if ($size === false || $size === null || $size <= 0) {
            throw InvalidVideoException::invalidMp4($title);
        }
        if ($size > $this->maxFileSize) {
            throw new ScormGenerationException('The MP4 file exceeds the configured SCORM size limit.');
        }

        $mimeType = $uploadedFile->getMimeType();
        if (!in_array($mimeType, ['video/mp4', 'application/mp4', 'application/octet-stream'], true)) {
            throw InvalidVideoException::invalidMp4($title);
        }
        if (!$this->hasMp4Signature($uploadedFile->getPathname())) {
            throw InvalidVideoException::invalidMp4($title);
        }
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

    private function storedFileExists(?string $path, string $title): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        try {
            $this->videoPathResolver->resolve($path, $title);

            return true;
        } catch (InvalidVideoException) {
            return false;
        }
    }

    private function deleteOldFile(string $path): void
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($normalizedPath, 'uploads/scorm/')) {
            return;
        }

        try {
            $absolutePath = $this->videoPathResolver->resolve($path, 'Previous SCORM video');
        } catch (InvalidVideoException) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
