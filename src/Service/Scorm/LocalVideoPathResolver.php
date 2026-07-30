<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Service\Scorm\Exception\InvalidVideoException;

final class LocalVideoPathResolver
{
    private readonly string $canonicalPublicDirectory;

    public function __construct(string $publicDirectory)
    {
        $canonicalDirectory = realpath($publicDirectory);
        if ($canonicalDirectory === false || !is_dir($canonicalDirectory)) {
            throw new \InvalidArgumentException('The public directory configured for SCORM videos is invalid.');
        }

        $this->canonicalPublicDirectory = rtrim($canonicalDirectory, '\\/');
    }

    public function resolve(string $location, string $videoTitle): string
    {
        $location = trim($location);
        if ($location === '') {
            throw InvalidVideoException::missing($videoTitle);
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            throw InvalidVideoException::remote($videoTitle);
        }

        $pathWithoutQuery = preg_split('/[?#]/', $location, 2)[0] ?? '';
        $decodedPath = rawurldecode($pathWithoutQuery);
        if (str_contains($decodedPath, "\0")) {
            throw InvalidVideoException::outsideAllowedDirectory($videoTitle);
        }

        if ($this->isAbsolutePath($decodedPath) && is_file($decodedPath)) {
            $candidate = $decodedPath;
        } else {
            $relativePath = ltrim(str_replace('\\', '/', $decodedPath), '/');
            if (str_starts_with($relativePath, 'public/')) {
                $relativePath = substr($relativePath, strlen('public/'));
            }
            $candidate = $this->canonicalPublicDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        }

        $canonicalPath = realpath($candidate);
        if ($canonicalPath === false || !is_file($canonicalPath)) {
            throw InvalidVideoException::missing($videoTitle);
        }
        if (!$this->isInsidePublicDirectory($canonicalPath)) {
            throw InvalidVideoException::outsideAllowedDirectory($videoTitle);
        }
        if (strtolower(pathinfo($canonicalPath, PATHINFO_EXTENSION)) !== 'mp4') {
            throw InvalidVideoException::invalidMp4($videoTitle);
        }

        return $canonicalPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function isInsidePublicDirectory(string $path): bool
    {
        $base = $this->normalizeCase($this->canonicalPublicDirectory).DIRECTORY_SEPARATOR;
        $candidate = $this->normalizeCase($path);

        return str_starts_with($candidate, $base);
    }

    private function normalizeCase(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }
}
