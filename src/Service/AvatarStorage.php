<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarStorage
{
    private const MAX_FILE_SIZE = 8 * 1024 * 1024;

    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $directory,
    ) {
    }

    public function save(User $user, UploadedFile $uploadedFile): void
    {
        $extension = $this->validate($uploadedFile);
        $filename = sprintf('avatar-%s.%s', bin2hex(random_bytes(12)), $extension);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('The avatar directory could not be created.');
        }

        try {
            $movedFile = $uploadedFile->move($this->directory, $filename);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('The profile photo could not be uploaded.', previous: $exception);
        }

        $oldFilename = $user->getAvatar();
        $user->setAvatar($filename);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $user->setAvatar($oldFilename);
            @unlink($movedFile->getPathname());

            throw new \RuntimeException('The profile photo could not be saved.', previous: $exception);
        }

        if ($oldFilename !== null && $oldFilename !== $filename && basename($oldFilename) === $oldFilename) {
            $oldPath = rtrim($this->directory, '\\/').DIRECTORY_SEPARATOR.$oldFilename;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    private function validate(UploadedFile $uploadedFile): string
    {
        if (!$uploadedFile->isValid()) {
            throw new \RuntimeException('The image upload did not complete correctly.');
        }

        $size = $uploadedFile->getSize();
        if ($size === false || $size === null || $size <= 0) {
            throw new \RuntimeException('Select a valid profile photo.');
        }
        if ($size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('The profile photo must be smaller than 8 MB.');
        }

        $mimeType = $uploadedFile->getMimeType();
        $extension = is_string($mimeType) ? self::EXTENSIONS_BY_MIME[$mimeType] ?? null : null;
        if ($extension === null || @getimagesize($uploadedFile->getPathname()) === false) {
            throw new \RuntimeException('Use a JPEG, PNG or WebP image.');
        }

        return $extension;
    }
}
