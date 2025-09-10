<?php

namespace App\Entity;

use App\Repository\QuizAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAnswerRepository::class)]
class QuizAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isCorrect = false;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    private ?QuizQuestion $question = null;

    public function getId(): ?int { return $this->id; }

    public function getText(): ?string { return $this->text; }
    public function setText(?string $text): self { $this->text = $text; return $this; }

    public function isCorrect(): bool { return $this->isCorrect; }
    public function setIsCorrect(bool $isCorrect): self { $this->isCorrect = $isCorrect; return $this; }

    public function getQuestion(): ?QuizQuestion { return $this->question; }
    public function setQuestion(?QuizQuestion $question): self { $this->question = $question; return $this; }
}
