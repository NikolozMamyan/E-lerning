<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Scorm\Exception\ScormGenerationException;
use App\Service\Scorm\ScormPackageGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scorm:generate-test',
    description: 'Generates a demonstration SCORM 1.2 package from local MP4 files.',
)]
final class GenerateTestScormPackageCommand extends Command
{
    public function __construct(private readonly ScormPackageGenerator $packageGenerator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Course title', 'Test course')
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Unique course identifier', 'test-course-001')
            ->addOption('video', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Local MP4 path (repeatable option)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output ZIP path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $videoPaths = array_values(array_filter(array_map('strval', (array) $input->getOption('video'))));

        if ($videoPaths === []) {
            $io->error('Add at least one --video option with a local MP4 path.');

            return Command::INVALID;
        }

        $title = trim((string) $input->getOption('title'));
        $identifier = trim((string) $input->getOption('identifier'));
        $outputPath = trim((string) ($input->getOption('output') ?? ''));
        if ($outputPath === '') {
            $outputPath = getcwd().DIRECTORY_SEPARATOR.'formation-test-scorm.zip';
        }

        $videos = [];
        foreach ($videoPaths as $index => $path) {
            $videos[] = [
                'title' => pathinfo($path, PATHINFO_FILENAME) ?: 'Video '.($index + 1),
                'path' => $path,
                'position' => $index + 1,
            ];
        }

        try {
            $archivePath = $this->packageGenerator->generate($identifier, $title, $videos, $outputPath);
        } catch (ScormGenerationException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $size = filesize($archivePath);
        $io->success('SCORM 1.2 package generated.');
        $io->definitionList(
            ['Path' => $archivePath],
            ['Final size' => $size === false ? 'unknown' : $this->formatBytes($size)],
            ['Videos' => (string) count($videos)],
            ['Completion threshold' => $this->packageGenerator->completionThreshold().' %'],
        );

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return sprintf('%.2f GB', $bytes / 1024 / 1024 / 1024);
        }
        if ($bytes >= 1024 * 1024) {
            return sprintf('%.2f MB', $bytes / 1024 / 1024);
        }

        return sprintf('%.2f KB', $bytes / 1024);
    }
}
