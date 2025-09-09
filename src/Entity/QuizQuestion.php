<?php

namespace App\Entity;

use App\Repository\QuizQuestionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: QuizQuestionRepository::class)]
class QuizQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column(type: 'text')]
    private string $question;

    #[ORM\ManyToOne(inversedBy: 'quizQuestions')]
    private ?Course $course = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: QuizAnswer::class, cascade: ['persist', 'remove'])]
    private Collection $answers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $question): self { $this->question = $question; return $this; }

    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): self { $this->course = $course; return $this; }

    /** @return Collection<int, QuizAnswer> */
    public function getAnswers(): Collection { return $this->answers; }
    public function addAnswer(QuizAnswer $answer): self
    {
        if (!$this->answers->contains($answer)) {
            $this->answers[] = $answer;
            $answer->setQuestion($this);
        }
        return $this;
    }
}
