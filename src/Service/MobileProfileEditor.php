<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Form\AddressType;
use App\Form\EducationType;
use App\Form\PersonalInfoType;
use App\Form\ProfessionalExperienceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;

final readonly class MobileProfileEditor
{
    public function __construct(private FormFactoryInterface $forms, private EntityManagerInterface $entityManager) {}

    public function update(User $user, string $section, array $data): array
    {
        if ($section === 'personal' && trim((string) ($data['username'] ?? '')) === '') return [['field' => 'username', 'message' => 'Enter your user name.']];
        $limits = ['username' => 180, 'phone' => 255, 'country' => 100, 'city' => 100, 'postalCode' => 20, 'taxId' => 50, 'bachelorDegree' => 255, 'masterDegree' => 255];
        foreach ($limits as $field => $limit) {
            if (isset($data[$field]) && is_string($data[$field]) && mb_strlen($data[$field]) > $limit) return [['field' => $field, 'message' => sprintf('Use at most %d characters for %s.', $limit, $field)]];
        }
        $type = match ($section) {
            'personal' => PersonalInfoType::class, 'address' => AddressType::class,
            'education' => EducationType::class, 'experience' => ProfessionalExperienceType::class,
            default => throw new \InvalidArgumentException('Unknown profile section.'),
        };
        $form = $this->forms->create($type, $user, ['csrf_protection' => false]);
        $form->submit($data);
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) $errors[] = ['field' => $error->getOrigin()?->getName(), 'message' => $error->getMessage()];
            return $errors;
        }
        $this->entityManager->flush();
        return [];
    }
}
