<?php

namespace App\Form;

use App\Entity\Video;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
    ->add('title', TextType::class, ['label' => 'Titre'])
    ->add('description', TextareaType::class, ['label' => 'Description'])
     ->add('duration', NumberType::class, [
        'label' => 'Durée (minutes)',
        'scale' => 2,          // 2 décimales max
        'html5' => true,
        'attr' => [
            'step' => '0.1',   // autorise 10.1, 10.25, etc.
            'min'  => '0.1',
        ],
    ])
    ->add('url', TextType::class, ['label' => 'URL de la vidéo']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Video::class]);
    }
}
