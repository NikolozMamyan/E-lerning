<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Scorm\Exception\ScormGenerationException;
use App\Service\Scorm\ScormPackageGenerator;
use App\Service\Scorm\ScormQuizParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scorm:generate-bilingual',
    description: 'Generates one bilingual English/German SCORM 1.2 package with localized videos and quizzes.',
)]
final class GenerateBilingualScormPackageCommand extends Command
{
    public function __construct(
        private readonly ScormPackageGenerator $packageGenerator,
        private readonly ScormQuizParser $quizParser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Course title', 'Bilingual compliance training')
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Unique course identifier', 'bilingual-compliance-training')
            ->addOption('video-en', null, InputOption::VALUE_REQUIRED, 'English MP4 path')
            ->addOption('video-de', null, InputOption::VALUE_REQUIRED, 'German MP4 path')
            ->addOption('quiz-en', null, InputOption::VALUE_REQUIRED, 'English quiz DOCX or JSON path')
            ->addOption('quiz-de', null, InputOption::VALUE_REQUIRED, 'German quiz DOCX or JSON path')
            ->addOption('title-en', null, InputOption::VALUE_REQUIRED, 'English training title')
            ->addOption('title-de', null, InputOption::VALUE_REQUIRED, 'German training title')
            ->addOption('pass-threshold', null, InputOption::VALUE_REQUIRED, 'Quiz pass percentage', '80')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output ZIP path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $paths = [
            'video-en' => trim((string) $input->getOption('video-en')),
            'video-de' => trim((string) $input->getOption('video-de')),
            'quiz-en' => trim((string) $input->getOption('quiz-en')),
            'quiz-de' => trim((string) $input->getOption('quiz-de')),
        ];

        foreach ($paths as $option => $path) {
            if ($path === '') {
                $io->error(sprintf('The --%s option is required.', $option));

                return Command::INVALID;
            }
        }

        $title = trim((string) $input->getOption('title'));
        $identifier = trim((string) $input->getOption('identifier'));
        $englishTitle = trim((string) ($input->getOption('title-en') ?: $title));
        $germanTitle = trim((string) ($input->getOption('title-de') ?: $title));
        $outputPath = trim((string) ($input->getOption('output') ?? ''));
        if ($outputPath === '') {
            $outputPath = getcwd().DIRECTORY_SEPARATOR.'bilingual-training-scorm.zip';
        }

        try {
            $archivePath = $this->packageGenerator->generateBilingual(
                $identifier,
                $title,
                [
                    'en' => [
                        'label' => 'English',
                        'title' => $englishTitle,
                        'videoPath' => $paths['video-en'],
                        'quiz' => $this->quizParser->parse($paths['quiz-en']),
                    ],
                    'de' => [
                        'label' => 'Deutsch',
                        'title' => $germanTitle,
                        'videoPath' => $paths['video-de'],
                        'quiz' => $this->quizParser->parse($paths['quiz-de']),
                    ],
                ],
                $outputPath,
                (int) $input->getOption('pass-threshold'),
            );
        } catch (ScormGenerationException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $size = filesize($archivePath);
        $io->success('Bilingual SCORM 1.2 package generated.');
        $io->definitionList(
            ['Path' => $archivePath],
            ['Final size' => $size === false ? 'unknown' : $this->formatBytes($size)],
            ['Languages' => 'English, Deutsch'],
            ['Quiz threshold' => (int) $input->getOption('pass-threshold').' %'],
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
