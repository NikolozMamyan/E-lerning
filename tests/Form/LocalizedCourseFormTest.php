<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\QuizQuestion;
use App\Entity\Video;
use App\Form\QuizAnswerType;
use App\Form\QuizQuestionType;
use App\Form\VideoType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class LocalizedCourseFormTest extends TestCase
{
    public function testVideoFormContainsFrenchAndItalianUrls(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addType(new VideoType())
            ->getFormFactory();
        $video = (new Video())
            ->setTitle('Video')
            ->setDescription(null)
            ->setDuration(60)
            ->setUrl('https://example.com/en');

        $form = $factory->create(VideoType::class, $video);

        self::assertTrue($form->has('urlFr'));
        self::assertTrue($form->has('urlIt'));
    }

    public function testQuizFormContainsFrenchAndItalianContent(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addType(new QuizAnswerType())
            ->addType(new QuizQuestionType())
            ->getFormFactory();

        $form = $factory->create(QuizQuestionType::class, new QuizQuestion());

        self::assertTrue($form->has('questionFr'));
        self::assertTrue($form->has('questionIt'));
        self::assertSame('__answer_name__', $form->get('answers')->getConfig()->getOption('prototype_name'));

        $answerForm = $form->get('answers')->get('0');
        self::assertTrue($answerForm->has('textFr'));
        self::assertTrue($answerForm->has('textIt'));
    }
}
