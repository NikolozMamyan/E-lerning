<?php

// src/Form/CoursePriceType.php

namespace App\Form;

use App\Entity\CoursePrice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CoursePriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('price', IntegerType::class, [
                'label' => 'Prix (en centimes)',
                'attr' => ['placeholder' => 'Ex: 1999 pour 19,99€'],
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => [
                    'Euro (€)' => 'EUR',
                    'Dollar ($)' => 'USD',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoursePrice::class,
        ]);
    }
}
