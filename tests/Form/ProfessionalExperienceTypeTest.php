<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\ProfessionalExperienceType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class ProfessionalExperienceTypeTest extends KernelTestCase
{
    public function testItMapsAProfessionalExperienceToTheUser(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $user = new User();
        $form = $formFactory->create(ProfessionalExperienceType::class, $user, ['csrf_protection' => false]);

        $form->submit([
            'professionalExperiences' => [[
                'title' => 'Managing Director',
                'employmentType' => 'Full-time',
                'companyName' => 'Example Company',
                'startDate' => '2024-02-01',
                'endDate' => '',
                'isCurrent' => '1',
                'location' => 'Luxembourg',
                'workplaceType' => 'On-site',
                'description' => 'Leading the company and its people strategy.',
            ]],
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertCount(1, $user->getProfessionalExperiences());

        $experience = $user->getProfessionalExperiences()->first();
        self::assertSame($user, $experience->getUser());
        self::assertInstanceOf(\DateTimeImmutable::class, $experience->getStartDate());
        self::assertTrue($experience->isCurrent());
        self::assertNull($experience->getEndDate());
    }
}
