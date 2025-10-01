<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class QuizQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $question = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'quizQuestions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\OneToMany(
        targetEntity: QuizAnswer::class, 
        mappedBy: 'question', 
        cascade: ['persist', 'remove'],  // 👈 Ajout des cascades
        orphanRemoval: true
    )]
    private Collection $answers;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question_fr = null;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

    // Getters et setters...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;
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

    /**
     * @return Collection<int, QuizAnswer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(QuizAnswer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this);  // 👈 Important : établir la relation inverse
        }
        return $this;
    }

    public function removeAnswer(QuizAnswer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }
        return $this;
    }

    public function getQuestionFr(): ?string
    {
        return $this->question_fr;
    }

    public function setQuestionFr(?string $question_fr): static
    {
        $this->question_fr = $question_fr;

        return $this;
    }
}