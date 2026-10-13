<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class LearnerContactService
{
    public function __construct(private MailerService $mailer, private ValidatorInterface $validator) {}

    public function send(array $data): void
    {
        $fields = [];
        foreach (['firstname', 'lastname', 'email', 'phone', 'message'] as $name) {
            if (isset($data[$name]) && !is_string($data[$name])) throw new \InvalidArgumentException('Invalid contact details.');
            $fields[$name] = trim($data[$name] ?? '');
        }
        $errors = $this->validator->validate($fields, new Assert\Collection([
            'firstname' => [new Assert\NotBlank(), new Assert\Length(max: 180)],
            'lastname' => [new Assert\NotBlank(), new Assert\Length(max: 180)],
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 254)],
            'phone' => [new Assert\Length(max: 50)],
            'message' => [new Assert\NotBlank(), new Assert\Length(max: 10000)],
        ]));
        if (count($errors)) throw new \InvalidArgumentException($errors[0]->getMessage());
        $this->mailer->send('contact@elearning-lesconsultants.com', '📩 Nouveau message de contact', 'emails/contact.html.twig', $fields);
    }
}
