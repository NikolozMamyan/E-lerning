<?php

declare(strict_types=1);

namespace App\Service\Scorm\Exception;

final class InvalidVideoException extends ScormGenerationException
{
    public static function missing(string $title): self
    {
        return new self(sprintf('The video "%s" could not be found.', $title));
    }

    public static function unreadable(string $title): self
    {
        return new self(sprintf('The video "%s" is not readable.', $title));
    }

    public static function invalidMp4(string $title): self
    {
        return new self(sprintf('The video "%s" is not a valid MP4 file.', $title));
    }

    public static function remote(string $title): self
    {
        return new self(sprintf('The video "%s" uses a remote URL. Only local MP4 files can be exported.', $title));
    }

    public static function outsideAllowedDirectory(string $title): self
    {
        return new self(sprintf('The video file "%s" is outside the allowed media directory.', $title));
    }
}
