<?php

declare(strict_types=1);

namespace App\Tests\Template;

use App\Entity\ProfessionalExperience;
use App\Entity\User;
use App\Form\ProfessionalExperienceType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

final class ProfessionalExperienceTemplateTest extends KernelTestCase
{
    public function testSettingsEditorRendersCollectionPrototypeAndExistingRole(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $user = (new User())
            ->setUsername('Alex Example')
            ->setEmail('alex@example.com')
            ->addProfessionalExperience(
                (new ProfessionalExperience())
                    ->setTitle('Managing Director')
                    ->setCompanyName('Example Company')
                    ->setEmploymentType('Full-time'),
            );

        $form = $container->get(FormFactoryInterface::class)->create(ProfessionalExperienceType::class, $user);
        $html = $container->get(Environment::class)->render('settings/_form.html.twig', [
            'form' => $form->createView(),
            'section' => 'experience',
        ]);

        self::assertStringContainsString('data-experience-collection', $html);
        self::assertStringContainsString('__name__', $html);
        self::assertStringContainsString('Managing Director', $html);
        self::assertStringContainsString('Add an experience', $html);
    }
}
