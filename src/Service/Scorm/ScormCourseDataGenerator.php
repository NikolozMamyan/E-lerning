<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Service\Scorm\Exception\ScormGenerationException;

final class ScormCourseDataGenerator
{
    public function generate(
        string $identifier,
        string $title,
        int $completionThreshold,
        int $commitIntervalSeconds,
        array $videos,
    ): string {
        try {
            return json_encode([
                'identifier' => $identifier,
                'title' => $title,
                'completionThreshold' => $completionThreshold,
                'commitIntervalSeconds' => $commitIntervalSeconds,
                'videos' => $videos,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (\JsonException $exception) {
            throw new ScormGenerationException('Unable to generate the course JSON data.', previous: $exception);
        }
    }

    public function generateBilingual(
        string $identifier,
        string $title,
        int $completionThreshold,
        int $commitIntervalSeconds,
        int $quizPassThreshold,
        array $languages,
    ): string {
        try {
            return json_encode([
                'identifier' => $identifier,
                'title' => $title,
                'mode' => 'bilingual-quiz',
                'completionThreshold' => $completionThreshold,
                'commitIntervalSeconds' => $commitIntervalSeconds,
                'quizPassThreshold' => $quizPassThreshold,
                'languages' => $languages,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (\JsonException $exception) {
            throw new ScormGenerationException('Unable to generate the bilingual course JSON data.', previous: $exception);
        }
    }
}
