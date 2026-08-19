<?php

declare(strict_types=1);

namespace App\Service;

final class UserImportCsvHeaderParser
{
    private const SUPPORTED_DELIMITERS = [',', ';'];

    /**
     * @param list<string> $expectedColumns
     *
     * @return array{delimiter: string, header: list<string>}|null
     */
    public function parse(string $headerLine, array $expectedColumns): ?array
    {
        if (trim($headerLine) === '') {
            return null;
        }

        $bestMatch = null;
        $bestScore = -1;
        $bestColumnCount = -1;

        foreach (self::SUPPORTED_DELIMITERS as $delimiter) {
            $header = array_map(
                static fn (string $column): string => trim($column),
                str_getcsv($headerLine, $delimiter, '"', '')
            );

            if (isset($header[0]) && str_starts_with($header[0], "\xEF\xBB\xBF")) {
                $header[0] = substr($header[0], 3);
            }

            $score = count(array_intersect($expectedColumns, $header));
            $columnCount = count($header);

            if ($score > $bestScore || ($score === $bestScore && $columnCount > $bestColumnCount)) {
                $bestMatch = [
                    'delimiter' => $delimiter,
                    'header' => $header,
                ];
                $bestScore = $score;
                $bestColumnCount = $columnCount;
            }
        }

        return $bestMatch;
    }
}
