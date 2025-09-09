<?php

namespace App\Entity;

use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
class QuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Course $course = null;

    #[ORM\Column(type: 'integer')]
    private int $score = 0; // en pourcentage

    #[ORM\Column(type: 'boolean')]
    private bool $passed = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\ManyToOne]
    private ?Enrollment $enrollment = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): self { $this->course = $course; return $this; }

    public function getScore(): int { return $this->score; }
    public function setScore(int $score): self { $this->score = $score; return $this; }

    public function isPassed(): bool { return $this->passed; }
    public function setPassed(bool $passed): self { $this->passed = $passed; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    public function getEnrollment(): ?Enrollment { return $this->enrollment; }
    public function setEnrollment(?Enrollment $enrollment): self { 
    $this->enrollment = $enrollment; 
    return $this; 
}
}
