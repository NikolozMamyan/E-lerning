<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Service\Scorm\Exception\ScormGenerationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BilingualScormExportService
{
    public function __construct(
        private readonly ScormPackageGenerator $packageGenerator,
        private readonly ScormQuizParser $quizParser,
        private readonly string $stagingDirectory,
        private readonly string $exportDirectory,
    ) {
    }

    public function export(
        string $identifier,
        string $title,
        string $englishTitle,
        string $germanTitle,
        int $passThreshold,
        array $uploads,
    ): string {
        foreach (['video_en', 'video_de', 'quiz_en', 'quiz_de'] as $field) {
            if (!isset($uploads[$field]) || !$uploads[$field] instanceof UploadedFile || !$uploads[$field]->isValid()) {
                throw new ScormGenerationException(sprintf('Select a valid file for %s.', str_replace('_', ' ', $field)));
            }
        }

        $this->assertExtension($uploads['video_en'], ['mp4'], 'English video');
        $this->assertExtension($uploads['video_de'], ['mp4'], 'German video');
        $this->assertExtension($uploads['quiz_en'], ['docx', 'json'], 'English quiz');
        $this->assertExtension($uploads['quiz_de'], ['docx', 'json'], 'German quiz');
        $this->ensureDirectory($this->stagingDirectory);
        $this->ensureDirectory($this->exportDirectory);
        $workingDirectory = rtrim($this->stagingDirectory, '\\/').DIRECTORY_SEPARATOR.'upload-'.bin2hex(random_bytes(12));

        if (!mkdir($workingDirectory, 0700, true) && !is_dir($workingDirectory)) {
            throw new ScormGenerationException('Unable to create the bilingual SCORM staging directory.');
        }

        try {
            $paths = [
                'video_en' => $this->move($uploads['video_en'], $workingDirectory, 'video-en.mp4'),
                'video_de' => $this->move($uploads['video_de'], $workingDirectory, 'video-de.mp4'),
                'quiz_en' => $this->move($uploads['quiz_en'], $workingDirectory, 'quiz-en.'.strtolower($uploads['quiz_en']->getClientOriginalExtension())),
                'quiz_de' => $this->move($uploads['quiz_de'], $workingDirectory, 'quiz-de.'.strtolower($uploads['quiz_de']->getClientOriginalExtension())),
            ];
            $outputPath = rtrim($this->exportDirectory, '\\/').DIRECTORY_SEPARATOR.'bilingual-'.bin2hex(random_bytes(12)).'.zip';

            return $this->packageGenerator->generateBilingual(
                $identifier,
                $title,
                [
                    'en' => [
                        'label' => 'English',
                        'title' => $englishTitle,
                        'videoPath' => $paths['video_en'],
                        'quiz' => $this->quizParser->parse($paths['quiz_en']),
                    ],
                    'de' => [
                        'label' => 'Deutsch',
                        'title' => $germanTitle,
                        'videoPath' => $paths['video_de'],
                        'quiz' => $this->quizParser->parse($paths['quiz_de']),
                    ],
                ],
                $outputPath,
                $passThreshold,
            );
        } finally {
            $this->removeDirectory($workingDirectory);
        }
    }

    private function assertExtension(UploadedFile $file, array $allowedExtensions, string $label): void
    {
        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions, true)) {
            throw new ScormGenerationException(sprintf('%s must use one of these formats: %s.', $label, strtoupper(implode(', ', $allowedExtensions))));
        }
    }

    private function move(UploadedFile $file, string $directory, string $filename): string
    {
        try {
            return $file->move($directory, $filename)->getPathname();
        } catch (\Throwable $exception) {
            throw new ScormGenerationException(sprintf('Unable to stage "%s".', $file->getClientOriginalName()), previous: $exception);
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
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
