<?php

declare(strict_types=1);

namespace App\Tests\Template;

use App\Entity\Course;
use App\Form\CoursePriceType;
use App\Form\QuizQuestionType;
use App\Form\VideoType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

final class AdminCourseEditorTemplateTest extends KernelTestCase
{
    public function testEditorRendersItsNavigationAndNestedPrototypes(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->createBuilder(FormType::class, null, ['csrf_protection' => false])
            ->add('title', TextType::class)
            ->add('category', ChoiceType::class, ['choices' => []])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('thumbFile', FileType::class, ['required' => false])
            ->add('planPdfFile', FileType::class, ['required' => false])
            ->add('coursePrices', CollectionType::class, [
                'entry_type' => CoursePriceType::class,
                'allow_add' => true,
                'prototype' => true,
            ])
            ->add('videos', CollectionType::class, [
                'entry_type' => VideoType::class,
                'allow_add' => true,
                'prototype' => true,
            ])
            ->add('quizQuestions', CollectionType::class, [
                'entry_type' => QuizQuestionType::class,
                'allow_add' => true,
                'prototype' => true,
            ])
            ->getForm();

        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->render('admin/_form.html.twig', [
            'form' => $form->createView(),
            'course' => new Course(),
            'button_label' => 'Create course',
        ]);

        self::assertStringContainsString('class="course-editor"', $html);
        self::assertStringContainsString('data-section-link="course-lessons"', $html);
        self::assertStringContainsString('data-target="#course_questions"', $html);
        self::assertStringContainsString('__answer_name__', $html);
        self::assertStringContainsString('form_videos___name___urlIt', $html);
        self::assertStringNotContainsString('id="form_coursePrices"', $html);
    }
}
