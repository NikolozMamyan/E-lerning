<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(
        targetEntity: CoursePrice::class, 
        mappedBy: 'course', 
        cascade: ['persist', 'remove'],  // 👈 Ajout des cascades
        orphanRemoval: true
    )]
    private Collection $coursePrices;

    #[ORM\OneToMany(
        targetEntity: Video::class, 
        mappedBy: 'course', 
        cascade: ['persist', 'remove'],  // 👈 Ajout des cascades
        orphanRemoval: true
    )]
    private Collection $videos;

    #[ORM\OneToMany(
        targetEntity: QuizQuestion::class, 
        mappedBy: 'course', 
        cascade: ['persist', 'remove'],  // 👈 Ajout des cascades
        orphanRemoval: true
    )]
    private Collection $quizQuestions;

    public function __construct()
    {
        $this->coursePrices = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->quizQuestions = new ArrayCollection();
    }

    // Getters et setters...
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Collection<int, CoursePrice>
     */
    public function getCoursePrices(): Collection
    {
        return $this->coursePrices;
    }

    public function addCoursePrice(CoursePrice $coursePrice): static
    {
        if (!$this->coursePrices->contains($coursePrice)) {
            $this->coursePrices->add($coursePrice);
            $coursePrice->setCourse($this);  // 👈 Important : établir la relation inverse
        }
        return $this;
    }

    public function removeCoursePrice(CoursePrice $coursePrice): static
    {
        if ($this->coursePrices->removeElement($coursePrice)) {
            if ($coursePrice->getCourse() === $this) {
                $coursePrice->setCourse(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setCourse($this);  // 👈 Important : établir la relation inverse
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getCourse() === $this) {
                $video->setCourse(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    public function getQuizQuestions(): Collection
    {
        return $this->quizQuestions;
    }

    public function addQuizQuestion(QuizQuestion $quizQuestion): static
    {
        if (!$this->quizQuestions->contains($quizQuestion)) {
            $this->quizQuestions->add($quizQuestion);
            $quizQuestion->setCourse($this);  // 👈 Important : établir la relation inverse
        }
        return $this;
    }

    public function removeQuizQuestion(QuizQuestion $quizQuestion): static
    {
        if ($this->quizQuestions->removeElement($quizQuestion)) {
            if ($quizQuestion->getCourse() === $this) {
                $quizQuestion->setCourse(null);
            }
        }
        return $this;
    }
}