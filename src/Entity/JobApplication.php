<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobApplicationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobApplicationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_job_application_member', fields: ['jobOffer', 'applicant'])]
class JobApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?JobOffer $jobOffer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $applicant = null;

    #[ORM\Column(length: 255)]
    private ?string $cvFilename = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFilename = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getJobOffer(): ?JobOffer { return $this->jobOffer; }
    public function setJobOffer(JobOffer $jobOffer): self { $this->jobOffer = $jobOffer; return $this; }
    public function getApplicant(): ?User { return $this->applicant; }
    public function setApplicant(User $applicant): self { $this->applicant = $applicant; return $this; }
    public function getCvFilename(): ?string { return $this->cvFilename; }
    public function setCvFilename(string $cvFilename): self { $this->cvFilename = $cvFilename; return $this; }
    public function getOriginalFilename(): ?string { return $this->originalFilename; }
    public function setOriginalFilename(string $originalFilename): self { $this->originalFilename = $originalFilename; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
