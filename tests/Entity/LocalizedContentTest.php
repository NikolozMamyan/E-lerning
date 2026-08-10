<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Entity\Video;
use PHPUnit\Framework\TestCase;

final class LocalizedContentTest extends TestCase
{
    public function testVideoUsesItalianUrlAndFallsBackToEnglish(): void
    {
        $video = (new Video())
            ->setUrl('https://example.com/video-en')
            ->setUrlIt('https://example.com/video-it');

        self::assertSame('https://example.com/video-it', $video->getUrlForLocale('it'));

        $video->setUrlIt(null);

        self::assertSame('https://example.com/video-en', $video->getUrlForLocale('it'));
    }

    public function testQuizQuestionUsesItalianTextAndFallsBackToEnglish(): void
    {
        $question = (new QuizQuestion())
            ->setQuestion('English question')
            ->setQuestionIt('Domanda italiana');

        self::assertSame('Domanda italiana', $question->getQuestionForLocale('it'));

        $question->setQuestionIt(null);

        self::assertSame('English question', $question->getQuestionForLocale('it'));
    }

    public function testQuizAnswerUsesItalianTextAndFallsBackToEnglish(): void
    {
        $answer = (new QuizAnswer())
            ->setText('English answer')
            ->setTextIt('Risposta italiana');

        self::assertSame('Risposta italiana', $answer->getTextForLocale('it'));

        $answer->setTextIt(null);

        self::assertSame('English answer', $answer->getTextForLocale('it'));
    }
}
