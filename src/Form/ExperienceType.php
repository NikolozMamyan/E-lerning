<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProfessionalExperience;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExperienceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Job title', 'row_attr' => ['class' => 'experience-field']])
            ->add('employmentType', ChoiceType::class, [
                'label' => 'Employment type',
                'required' => false,
                'placeholder' => 'Select a type',
                'choices' => array_combine(
                    ['Full-time', 'Part-time', 'Self-employed', 'Freelance', 'Contract', 'Internship', 'Apprenticeship', 'Seasonal'],
                    ['Full-time', 'Part-time', 'Self-employed', 'Freelance', 'Contract', 'Internship', 'Apprenticeship', 'Seasonal'],
                ),
                'row_attr' => ['class' => 'experience-field'],
            ])
            ->add('companyName', TextType::class, ['label' => 'Company', 'row_attr' => ['class' => 'experience-field']])
            ->add('startDate', DateType::class, [
                'label' => 'Start date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'row_attr' => ['class' => 'experience-field'],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'row_attr' => ['class' => 'experience-field'],
            ])
            ->add('isCurrent', CheckboxType::class, [
                'label' => 'I currently work here',
                'required' => false,
                'row_attr' => ['class' => 'experience-field experience-field--wide experience-field--checkbox'],
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'required' => false,
                'row_attr' => ['class' => 'experience-field'],
            ])
            ->add('workplaceType', ChoiceType::class, [
                'label' => 'Workplace type',
                'required' => false,
                'placeholder' => 'Select a workplace type',
                'choices' => [
                    'On-site' => 'On-site',
                    'Hybrid' => 'Hybrid',
                    'Remote' => 'Remote',
                ],
                'row_attr' => ['class' => 'experience-field'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 3000],
                'row_attr' => ['class' => 'experience-field experience-field--wide'],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
                $experience = $event->getData();
                if ($experience instanceof ProfessionalExperience && $experience->isCurrent()) {
                    $experience->setEndDate(null);
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProfessionalExperience::class]);
    }
}
