<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\ChangePasswordFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;

final class ChangePasswordFormTypeTest extends TestCase
{
    public function testPasswordMinimumLengthIsFiveCharacters(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::once())
            ->method('add')
            ->with(
                'plainPassword',
                RepeatedType::class,
                self::callback(static function (array $options): bool {
                    foreach ($options['first_options']['constraints'] ?? [] as $constraint) {
                        if ($constraint instanceof Length) {
                            return $constraint->min === 5;
                        }
                    }

                    return false;
                })
            )
            ->willReturnSelf();

        (new ChangePasswordFormType())->buildForm($builder, []);
    }
}
