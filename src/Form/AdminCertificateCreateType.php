<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminCertificateCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('users', EntityType::class, [
                'class' => User::class,
                'label' => 'Utilisateurs',
                'multiple' => true,
                'expanded' => false,
                'choice_label' => static fn (User $user): string => sprintf(
                    '%s (%s)',
                    $user->getUsername(),
                    $user->getEmail() ?? 'no-email'
                ),
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('u')
                    ->orderBy('u.username', 'ASC')
                    ->addOrderBy('u.email', 'ASC'),
                'attr' => [
                    'size' => 12,
                ],
            ])
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'label' => 'Cours',
                'choice_label' => 'title',
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('c')
                    ->orderBy('c.title', 'ASC'),
            ])
            ->add('durationLabel', TextType::class, [
                'label' => 'Durée',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 1H30',
                ],
            ])
            ->add('trainerName', TextType::class, [
                'label' => 'Formateur',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Barbora Kubikova - LES CONSULTANTS',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
