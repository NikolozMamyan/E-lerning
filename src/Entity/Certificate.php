<?php

namespace App\Entity;

use App\Repository\CertificateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CertificateRepository::class)]
class Certificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\ManyToOne(inversedBy: 'certificates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $ref = null;

    #[ORM\ManyToOne(inversedBy: 'certificates')]
    private ?User $passed = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $durationLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trainerName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(string $ref): static
    {
        $this->ref = $ref;

        return $this;
    }

    public function getPassed(): ?User
    {
        return $this->passed;
    }

    public function setPassed(?User $passed): static
    {
        $this->passed = $passed;

        return $this;
    }

    public function getDurationLabel(): ?string
    {
        return $this->durationLabel;
    }

    public function setDurationLabel(?string $durationLabel): static
    {
        $this->durationLabel = $durationLabel;

        return $this;
    }

    public function getTrainerName(): ?string
    {
        return $this->trainerName;
    }

    public function setTrainerName(?string $trainerName): static
    {
        $this->trainerName = $trainerName;

        return $this;
    }
}
