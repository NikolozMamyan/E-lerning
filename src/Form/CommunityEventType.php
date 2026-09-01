<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CommunityEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CommunityEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, ['label' => 'Event title', 'attr' => ['placeholder' => 'e.g. AML regulatory breakfast']])
            ->add('description', TextareaType::class, [
                'sanitize_html' => true,
                'sanitizer' => 'app.community_content_sanitizer',
                'attr' => [
                    'rows' => 7,
                    'placeholder' => 'Programme and practical information…',
                    'data-community-rich-text' => '',
                    'class' => 'admin-community__rich-source',
                ],
            ])
            ->add('startsAt', DateTimeType::class, ['label' => 'Date and time', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('location', null, ['required' => false, 'attr' => ['placeholder' => 'Luxembourg or online']])
            ->add('isPublished', CheckboxType::class, ['label' => 'Publish in the agenda', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CommunityEvent::class]);
    }
}
