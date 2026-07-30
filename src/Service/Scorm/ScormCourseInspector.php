<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Entity\Course;
use App\Entity\Video;
use App\Enum\ScormLanguage;
use App\Service\Scorm\Exception\InvalidVideoException;
use App\Service\Scorm\Exception\ScormGenerationException;

final class ScormCourseInspector
{
    public function __construct(private readonly LocalVideoPathResolver $videoPathResolver)
    {
    }

    public function singleVideo(Course $course): Video
    {
        $videos = array_values($course->getVideos()->toArray());

        if ($videos === []) {
            throw new ScormGenerationException('This course does not contain a video.');
        }
        if (count($videos) !== 1) {
            throw new ScormGenerationException('SCORM export requires exactly one video per course.');
        }

        return $videos[0];
    }

    public function languageStatuses(Course $course): array
    {
        $video = $this->singleVideo($course);
        $statuses = [];

        foreach (ScormLanguage::cases() as $language) {
            $file = trim((string) $language->file($video));
            $title = trim((string) $language->title($video));
            $fileExists = false;

            if ($file !== '') {
                try {
                    $this->videoPathResolver->resolve($file, $title !== '' ? $title : $language->label());
                    $fileExists = true;
                } catch (InvalidVideoException) {
                    $fileExists = false;
                }
            }

            $statuses[] = [
                'language' => $language,
                'title' => $title,
                'file' => $file,
                'filename' => $file !== '' ? basename(str_replace('\\', '/', $file)) : null,
                'fileExists' => $fileExists,
                'ready' => $fileExists && $title !== '',
            ];
        }

        return $statuses;
    }
}
