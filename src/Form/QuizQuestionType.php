<?php

namespace App\Form;

use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Form\QuizAnswerType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class QuizQuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', TextareaType::class, ['label' => 'Question'])
            ->add('answers', CollectionType::class, [
                'entry_type' => QuizAnswerType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                // 👉 Ici on met un QuizAnswer, pas un QuizQuestion
                'prototype_data' => new QuizAnswer(),
            ]);

        // Préremplir les nouvelles questions avec 4 réponses vides
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $question = $event->getData();
            if ($question && count($question->getAnswers()) === 0) {
                for ($i = 0; $i < 4; $i++) {
                    $question->addAnswer(new QuizAnswer());
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => QuizQuestion::class]);
    }
}
