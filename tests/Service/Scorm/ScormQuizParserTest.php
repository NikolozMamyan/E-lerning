<?php

declare(strict_types=1);

namespace App\Tests\Service\Scorm;

use App\Service\Scorm\ScormQuizParser;
use PHPUnit\Framework\TestCase;

final class ScormQuizParserTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scorm-quiz-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testParsesMultipleAnswerDocxInEnglishAndGermanLayout(): void
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'quiz.docx';
        $paragraphs = [
            'Compliance quiz',
            '20 Questions — One or More Correct Answers',
            'Question 1',
            'Which controls apply?',
            'A. Screening.B. Monitoring.C. No controls.D. Escalation.',
            'Correct answer(s): A, B, D',
            'Frage 2',
            'Was ist erforderlich?',
            'A. Prüfen.',
            'B. Dokumentieren.',
            'C. Ignorieren.',
            'D. Eskalieren.',
            'Richtige Antwort(en): A, B, D',
        ];
        $this->createDocx($path, $paragraphs);

        $quiz = (new ScormQuizParser())->parse($path);

        self::assertSame('Compliance quiz', $quiz['title']);
        self::assertCount(2, $quiz['questions']);
        self::assertSame(['A', 'B', 'D'], $quiz['questions'][0]['correct']);
        self::assertSame(['Screening.', 'Monitoring.', 'No controls.', 'Escalation.'], array_column($quiz['questions'][0]['options'], 'text'));
        self::assertSame('Was ist erforderlich?', $quiz['questions'][1]['text']);
    }

    public function testParsesNormalizedJsonQuiz(): void
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'quiz.json';
        file_put_contents($path, json_encode([
            'title' => 'Test',
            'questions' => [[
                'question' => 'Choose the valid answer',
                'options' => ['A' => 'Valid', 'B' => 'Invalid'],
                'correctAnswers' => ['A'],
            ]],
        ], JSON_THROW_ON_ERROR));

        $quiz = (new ScormQuizParser())->parse($path);

        self::assertSame('Test', $quiz['title']);
        self::assertSame('q1', $quiz['questions'][0]['id']);
        self::assertSame(['A'], $quiz['questions'][0]['correct']);
    }

    private function createDocx(string $path, array $paragraphs): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL));
        $body = implode('', array_map(static function (string $paragraph): string {
            return '<w:p><w:r><w:t>'.htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
        }, $paragraphs));
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        self::assertTrue($zip->close());
    }
}
