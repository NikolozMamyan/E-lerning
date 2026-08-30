<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JobApplicationCvStorage
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    private const EXTENSIONS_BY_MIME = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public function __construct(private readonly string $directory)
    {
    }

    public function store(UploadedFile $file): string
    {
        $extension = $this->validate($file);
        $filename = sprintf('cv-%s.%s', bin2hex(random_bytes(16)), $extension);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('The private CV directory could not be created.');
        }

        try {
            $file->move($this->directory, $filename);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('The CV could not be uploaded.', previous: $exception);
        }

        return $filename;
    }

    public function path(string $filename): string
    {
        if (basename($filename) !== $filename) {
            throw new \RuntimeException('Invalid CV filename.');
        }

        $path = rtrim($this->directory, '\\/').DIRECTORY_SEPARATOR.$filename;
        if (!is_file($path)) {
            throw new \RuntimeException('The CV file was not found.');
        }

        return $path;
    }

    public function remove(string $filename): void
    {
        if (basename($filename) !== $filename) {
            return;
        }

        $path = rtrim($this->directory, '\\/').DIRECTORY_SEPARATOR.$filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function validate(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('The CV upload did not complete correctly.');
        }

        $size = $file->getSize();
        if ($size === false || $size === null || $size <= 0) {
            throw new \RuntimeException('Select a valid CV file.');
        }
        if ($size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('The CV must be smaller than 5 MB.');
        }

        $mimeType = $file->getMimeType();
        $extension = is_string($mimeType) ? self::EXTENSIONS_BY_MIME[$mimeType] ?? null : null;
        if ($extension === null) {
            throw new \RuntimeException('Use a PDF, DOC or DOCX document.');
        }

        return $extension;
    }
}
