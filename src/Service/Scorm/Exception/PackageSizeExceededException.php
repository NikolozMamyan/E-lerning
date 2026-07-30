<?php

declare(strict_types=1);

namespace App\Service\Scorm\Exception;

final class PackageSizeExceededException extends ScormGenerationException
{
    public function __construct(int $estimatedSize, int $maximumSize)
    {
        parent::__construct(sprintf(
            'The estimated package size (%s) exceeds the allowed limit (%s).',
            self::formatBytes($estimatedSize),
            self::formatBytes($maximumSize),
        ));
    }

    private static function formatBytes(int $bytes): string
    {
        return sprintf('%.2f GB', $bytes / 1024 / 1024 / 1024);
    }
}
