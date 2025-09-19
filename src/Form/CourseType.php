<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        
$builder
    ->add('title', TextType::class, [
        'label' => 'Titre',
    ])
    ->add('description', TextareaType::class, [
        'label' => 'Description',
        'required' => false,
    ])
     ->add('thumbFile', VichFileType::class, [
        'required' => false,
        'label' => 'Miniature (image)',
        'download_uri' => false,
    ])
    ->add('coursePrices', CollectionType::class, [
        'entry_type' => CoursePriceType::class,
        'allow_add' => true,
        'allow_delete' => true,
        'by_reference' => false,
        'prototype' => true,
        'label' => 'Tarifs',
    ])
    ->add('videos', CollectionType::class, [
        'entry_type' => VideoType::class,
        'allow_add' => true,
        'allow_delete' => true,
        'by_reference' => false,
        'prototype' => true,
        'label' => false,
    ])
    ->add('quizQuestions', CollectionType::class, [
        'entry_type' => QuizQuestionType::class,
        'allow_add' => true,
        'allow_delete' => true,
        'by_reference' => false,
        'prototype' => true,
        'label' => false,
    ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}
