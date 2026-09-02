<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\QuizAttempt;
use ZipArchive;

final class CertificateArchiveGenerator
{
    public function __construct(private readonly CertificatePdfGenerator $certificatePdfGenerator)
    {
    }

    /** @param iterable<QuizAttempt> $attempts */
    public function generate(iterable $attempts): string
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'company-certificates-');
        if ($archivePath === false) {
            throw new \RuntimeException('Unable to create the certificate archive.');
        }

        $archive = new ZipArchive();
        $archiveOpened = false;

        try {
            $openResult = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new \RuntimeException('Unable to open the certificate archive.');
            }
            $archiveOpened = true;
            $usedFilenames = [];

            foreach ($attempts as $index => $attempt) {
                $document = $this->certificatePdfGenerator->generate($attempt);
                $filename = $this->uniqueFilename($document['filename'], $usedFilenames, (int) $index);
                $usedFilenames[$filename] = true;

                if (!$archive->addFromString($filename, $document['content'])) {
                    throw new \RuntimeException('Unable to add a certificate to the archive.');
                }
            }

            if (!$archive->close()) {
                throw new \RuntimeException('Unable to finalize the certificate archive.');
            }
            $archiveOpened = false;
        } catch (\Throwable $exception) {
            if ($archiveOpened) {
                $archive->close();
            }
            if (is_file($archivePath)) {
                unlink($archivePath);
            }

            throw $exception;
        }

        return $archivePath;
    }

    /**
     * @param array<string, true> $usedFilenames
     */
    private function uniqueFilename(string $filename, array $usedFilenames, int $index): string
    {
        if (!isset($usedFilenames[$filename])) {
            return $filename;
        }

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return sprintf('%s-%d.%s', $basename, $index + 1, $extension);
    }
}
