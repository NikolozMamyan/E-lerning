<?php

namespace App\Form;

use App\Entity\QuizAnswer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuizAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextType::class, [
                'label' => 'Answer (English)',
            ])
            ->add('textFr', TextType::class, [
                'label' => 'Answer (French)',
                'required' => false,
            ])
            ->add('textIt', TextType::class, [
                'label' => 'Answer (Italian)',
                'required' => false,
            ])
            ->add('isCorrect', CheckboxType::class, [
                'label' => 'Bonne réponse ?',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuizAnswer::class,
        ]);
    }
}
