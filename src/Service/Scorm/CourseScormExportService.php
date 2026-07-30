<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Entity\Course;
use App\Enum\ScormLanguage;
use App\Service\Scorm\Exception\ScormGenerationException;

final class CourseScormExportService
{
    public function __construct(
        private readonly LocalVideoPathResolver $videoPathResolver,
        private readonly ScormCourseInspector $courseInspector,
        private readonly ScormPackageGenerator $packageGenerator,
        private readonly string $exportDirectory,
    ) {
    }

    public function export(Course $course, ScormLanguage $language): string
    {
        $video = $this->courseInspector->singleVideo($course);
        $title = trim((string) $language->title($video));
        if ($title === '') {
            throw new ScormGenerationException(sprintf('The %s SCORM title is missing.', $language->label()));
        }

        $storedFile = trim((string) $language->file($video));
        if ($storedFile === '') {
            throw new ScormGenerationException(sprintf('The %s SCORM video is missing.', $language->label()));
        }
        $videos = [[
            'title' => $title,
            'path' => $this->videoPathResolver->resolve($storedFile, $title),
            'position' => 1,
        ]];

        if (!is_dir($this->exportDirectory) && !mkdir($this->exportDirectory, 0775, true) && !is_dir($this->exportDirectory)) {
            throw new ScormGenerationException('Unable to create the SCORM export directory.');
        }

        $identifier = 'course-'.($course->getId() ?? 'new').'-'.$language->value;
        $outputPath = rtrim($this->exportDirectory, '\\/').DIRECTORY_SEPARATOR.$identifier.'-'.bin2hex(random_bytes(10)).'.zip';

        return $this->packageGenerator->generate($identifier, $title, $videos, $outputPath);
    }
}
