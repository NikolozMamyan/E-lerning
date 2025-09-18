<?php

namespace App\Form;

use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
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
            ->add('question', TextareaType::class, [
                'label' => 'Question',
                'attr' => ['rows' => 3]
            ])
            ->add('answers', CollectionType::class, [
                'entry_type' => QuizAnswerType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'attr' => ['class' => 'answers-collection'],
                // Retire prototype_data d'ici, c'est géré par l'event listener
            ]);

        // Amélioration de l'event listener
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $question = $event->getData();
            
            // Si c'est une nouvelle question (pas encore persistée)
            if ($question && $question->getId() === null && count($question->getAnswers()) === 0) {
                for ($i = 0; $i < 4; $i++) {
                    $answer = new QuizAnswer();
                    $answer->setQuestion($question); // Important : établir la relation
                    $question->addAnswer($answer);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => QuizQuestion::class]);
    }
}