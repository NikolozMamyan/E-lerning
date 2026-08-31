<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ProfessionalExperience;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ProfessionalExperienceTest extends TestCase
{
    public function testUserMaintainsBothSidesOfExperienceRelation(): void
    {
        $user = new User();
        $experience = (new ProfessionalExperience())
            ->setTitle('Managing Director')
            ->setCompanyName('Example Company');

        $user->addProfessionalExperience($experience);

        self::assertTrue($user->getProfessionalExperiences()->contains($experience));
        self::assertSame($user, $experience->getUser());

        $user->removeProfessionalExperience($experience);

        self::assertFalse($user->getProfessionalExperiences()->contains($experience));
        self::assertNull($experience->getUser());
    }

    public function testEndDateCannotPrecedeStartDate(): void
    {
        $experience = (new ProfessionalExperience())
            ->setTitle('Managing Director')
            ->setCompanyName('Example Company')
            ->setStartDate(new \DateTimeImmutable('2026-05-01'))
            ->setEndDate(new \DateTimeImmutable('2026-04-01'));

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $violations = $validator->validate($experience);

        self::assertCount(1, $violations);
        self::assertSame('endDate', $violations[0]->getPropertyPath());
    }

    public function testCurrentRoleClearsEndDate(): void
    {
        $experience = (new ProfessionalExperience())
            ->setEndDate(new \DateTimeImmutable('2026-08-01'))
            ->setIsCurrent(true);

        self::assertTrue($experience->isCurrent());
        self::assertNull($experience->getEndDate());
    }
}
