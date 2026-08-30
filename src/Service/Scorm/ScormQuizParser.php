<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Service\Scorm\Exception\ScormGenerationException;

final class ScormQuizParser
{
    public function parse(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ScormGenerationException(sprintf('The quiz file "%s" could not be read.', basename($path)));
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'docx' => $this->parseDocx($path),
            'json' => $this->parseJson($path),
            default => throw new ScormGenerationException('Quiz files must use the DOCX or JSON format.'),
        };
    }

    public function encode(array $quiz): string
    {
        try {
            return json_encode(
                $this->normalize($quiz),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
            );
        } catch (\JsonException $exception) {
            throw new ScormGenerationException('Unable to encode the quiz data.', previous: $exception);
        }
    }

    private function parseJson(string $path): array
    {
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ScormGenerationException(sprintf('The quiz JSON "%s" is invalid.', basename($path)), previous: $exception);
        }

        if (!is_array($data)) {
            throw new ScormGenerationException('The quiz JSON root must be an object.');
        }

        return $this->normalize($data);
    }

    private function parseDocx(string $path): array
    {
        $archive = new \ZipArchive();
        if ($archive->open($path) !== true) {
            throw new ScormGenerationException(sprintf('The quiz document "%s" could not be opened.', basename($path)));
        }

        try {
            $xml = $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
        }

        if (!is_string($xml) || $xml === '') {
            throw new ScormGenerationException('The DOCX quiz does not contain a readable document body.');
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                throw new ScormGenerationException('The DOCX quiz content is invalid.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $lines = [];

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            $parts = [];
            foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $textNode) {
                $parts[] = $textNode->textContent;
            }
            $line = trim(implode('', $parts));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $this->parseLines($lines);
    }

    private function parseLines(array $lines): array
    {
        $title = trim((string) ($lines[0] ?? 'Quiz'));
        $questions = [];
        $count = count($lines);

        for ($index = 0; $index < $count; ++$index) {
            if (preg_match('/^(?:Question|Frage)\s+(\d+)\b/ui', $lines[$index], $questionMatch) !== 1) {
                continue;
            }

            $questionNumber = (int) $questionMatch[1];
            $questionText = trim((string) ($lines[++$index] ?? ''));
            $optionLines = [];

            while ($index + 1 < $count && preg_match('/^(?:Correct answer\(s\)|Richtige Antwort\(en\))\s*:/ui', $lines[$index + 1]) !== 1) {
                $optionLines[] = $lines[++$index];
            }

            $answerLine = $index + 1 < $count ? $lines[++$index] : '';
            preg_match_all('/\b([A-D])\b/u', $answerLine, $correctMatches);

            $questions[] = [
                'id' => 'q'.$questionNumber,
                'text' => $questionText,
                'options' => $this->parseOptions(implode(' ', $optionLines), $questionNumber),
                'correct' => array_values(array_unique($correctMatches[1] ?? [])),
            ];
        }

        return $this->normalize([
            'title' => $title,
            'questions' => $questions,
        ]);
    }

    private function parseOptions(string $rawOptions, int $questionNumber): array
    {
        preg_match_all('/(?<!\pL)([A-D])\.\s*/u', $rawOptions, $markers, PREG_OFFSET_CAPTURE);
        $labels = $markers[1] ?? [];
        $fullMarkers = $markers[0] ?? [];
        $options = [];

        foreach ($labels as $index => $labelMatch) {
            $markerOffset = $fullMarkers[$index][1];
            $textStart = $markerOffset + strlen($fullMarkers[$index][0]);
            $textEnd = isset($fullMarkers[$index + 1]) ? $fullMarkers[$index + 1][1] : strlen($rawOptions);
            $options[] = [
                'id' => $labelMatch[0],
                'text' => trim(substr($rawOptions, $textStart, $textEnd - $textStart)),
            ];
        }

        if (count($options) < 2) {
            throw new ScormGenerationException(sprintf('Question %d does not contain enough answer options.', $questionNumber));
        }

        return $options;
    }

    private function normalize(array $quiz): array
    {
        $questions = $quiz['questions'] ?? null;
        if (!is_array($questions) || $questions === []) {
            throw new ScormGenerationException('The quiz must contain at least one question.');
        }

        $normalizedQuestions = [];
        foreach (array_values($questions) as $index => $question) {
            if (!is_array($question)) {
                throw new ScormGenerationException(sprintf('Quiz question %d is invalid.', $index + 1));
            }

            $text = trim((string) ($question['text'] ?? $question['question'] ?? ''));
            $rawOptions = $question['options'] ?? [];
            $rawCorrect = $question['correct'] ?? $question['correctAnswers'] ?? [];
            if (is_string($rawCorrect)) {
                preg_match_all('/\b([A-Z])\b/u', strtoupper($rawCorrect), $matches);
                $rawCorrect = $matches[1] ?? [];
            }

            $options = [];
            if (is_array($rawOptions)) {
                foreach ($rawOptions as $key => $option) {
                    if (is_array($option)) {
                        $optionId = trim((string) ($option['id'] ?? $option['value'] ?? $key));
                        $optionText = trim((string) ($option['text'] ?? $option['label'] ?? ''));
                    } else {
                        $optionId = is_string($key) ? trim($key) : chr(65 + count($options));
                        $optionText = trim((string) $option);
                    }
                    if ($optionId !== '' && $optionText !== '') {
                        $options[] = ['id' => strtoupper($optionId), 'text' => $optionText];
                    }
                }
            }

            $optionIds = array_column($options, 'id');
            $correct = array_values(array_unique(array_map(static fn (mixed $value): string => strtoupper(trim((string) $value)), is_array($rawCorrect) ? $rawCorrect : [])));

            if ($text === '' || count($options) < 2 || $correct === [] || array_diff($correct, $optionIds) !== []) {
                throw new ScormGenerationException(sprintf('Quiz question %d is incomplete or has invalid correct answers.', $index + 1));
            }

            $normalizedQuestions[] = [
                'id' => trim((string) ($question['id'] ?? 'q'.($index + 1))),
                'text' => $text,
                'options' => $options,
                'correct' => $correct,
            ];
        }

        return [
            'title' => trim((string) ($quiz['title'] ?? 'Knowledge check')) ?: 'Knowledge check',
            'questions' => $normalizedQuestions,
        ];
    }
}
