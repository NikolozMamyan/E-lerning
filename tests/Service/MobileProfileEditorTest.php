<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ProfessionalExperience;
use App\Entity\User;
use App\Service\MobileProfileEditor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;

final class MobileProfileEditorTest extends TestCase
{
    private function editor(): MobileProfileEditor
    {
        $forms = Forms::createFormFactoryBuilder()->addExtension(new CsrfExtension(new CsrfTokenManager()))->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        return new MobileProfileEditor($forms, $em);
    }

    public function testRemovingFirstExperienceKeepsSecondEntityAndItsCompany(): void
    {
        $first = (new ProfessionalExperience())->setTitle('First')->setCompanyName('Company A')->setStartDate(new \DateTimeImmutable('2020-01-01'));
        $second = (new ProfessionalExperience())->setTitle('Second')->setCompanyName('Company B')->setStartDate(new \DateTimeImmutable('2021-01-01'));
        $user = (new User())->addProfessionalExperience($first)->addProfessionalExperience($second);
        $errors = $this->editor()->update($user, 'experience', ['professionalExperiences' => ['1' => ['title' => 'Second updated', 'companyName' => 'Company B', 'startDate' => '2021-01-01', 'isCurrent' => true]]]);
        self::assertSame([], $errors);
        self::assertCount(1, $user->getProfessionalExperiences());
        self::assertSame($second, $user->getProfessionalExperiences()->first());
        self::assertSame('Second updated', $second->getTitle());
    }

    public function testCurrentExperienceClearsEndDateThroughExistingForm(): void
    {
        $experience = (new ProfessionalExperience())->setTitle('Role')->setCompanyName('Company')->setStartDate(new \DateTimeImmutable('2020-01-01'))->setEndDate(new \DateTimeImmutable('2021-01-01'));
        $user = (new User())->addProfessionalExperience($experience);
        $errors = $this->editor()->update($user, 'experience', ['professionalExperiences' => ['0' => ['title' => 'Role', 'companyName' => 'Company', 'startDate' => '2020-01-01', 'endDate' => '2021-01-01', 'isCurrent' => true]]]);
        self::assertSame([], $errors);
        self::assertTrue($experience->isCurrent());
        self::assertNull($experience->getEndDate());
    }
}
