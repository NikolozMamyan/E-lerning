<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\JobOffer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JobOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, ['label' => 'Job title', 'attr' => ['placeholder' => 'e.g. Compliance Officer']])
            ->add('description', TextareaType::class, ['attr' => ['rows' => 8, 'placeholder' => 'Role, missions, profile and application details…']])
            ->add('isActive', CheckboxType::class, ['label' => 'Publish as the current opportunity', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobOffer::class]);
    }
}
