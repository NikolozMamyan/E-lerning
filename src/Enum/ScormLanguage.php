<?php

declare(strict_types=1);

namespace App\Enum;

use App\Entity\Video;

enum ScormLanguage: string
{
    case English = 'en';
    case French = 'fr';
    case German = 'de';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'French',
            self::German => 'German',
        };
    }

    public function file(Video $video): ?string
    {
        return match ($this) {
            self::English => $video->getScormEn(),
            self::French => $video->getScormFr(),
            self::German => $video->getScormDe(),
        };
    }

    public function title(Video $video): ?string
    {
        return match ($this) {
            self::English => $video->getScormTitleEn(),
            self::French => $video->getScormTitleFr(),
            self::German => $video->getScormTitleDe(),
        };
    }

    public function assign(Video $video, ?string $file, ?string $title): void
    {
        match ($this) {
            self::English => $video->setScormEn($file)->setScormTitleEn($title),
            self::French => $video->setScormFr($file)->setScormTitleFr($title),
            self::German => $video->setScormDe($file)->setScormTitleDe($title),
        };
    }
}
