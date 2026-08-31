<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
class ProfessionalExperience
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'professionalExperiences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $title = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $employmentType = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $companyName = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private bool $isCurrent = false;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $location = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $workplaceType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $description = null;

    public function __construct()
    {
        $this->startDate = new \DateTimeImmutable('first day of this month');
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getEmploymentType(): ?string { return $this->employmentType; }
    public function setEmploymentType(?string $employmentType): self { $this->employmentType = $employmentType; return $this; }
    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(string $companyName): self { $this->companyName = $companyName; return $this; }
    public function getStartDate(): ?\DateTimeImmutable { return $this->startDate; }
    public function setStartDate(\DateTimeImmutable $startDate): self { $this->startDate = $startDate; return $this; }
    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $endDate): self { $this->endDate = $endDate; return $this; }
    public function isCurrent(): bool { return $this->isCurrent; }
    public function setIsCurrent(bool $isCurrent): self
    {
        $this->isCurrent = $isCurrent;
        if ($isCurrent) {
            $this->endDate = null;
        }

        return $this;
    }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }
    public function getWorkplaceType(): ?string { return $this->workplaceType; }
    public function setWorkplaceType(?string $workplaceType): self { $this->workplaceType = $workplaceType; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (!$this->isCurrent && $this->startDate !== null && $this->endDate !== null && $this->endDate < $this->startDate) {
            $context->buildViolation('The end date must be after the start date.')
                ->atPath('endDate')
                ->addViolation();
        }
    }
}
