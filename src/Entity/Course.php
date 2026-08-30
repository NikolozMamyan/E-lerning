<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Vich\Uploadable]
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
        cascade: ['persist', 'remove'],  
        orphanRemoval: true
    )]
    private Collection $coursePrices;

    #[ORM\OneToMany(
        targetEntity: Video::class, 
        mappedBy: 'course', 
        cascade: ['persist', 'remove'],  
        orphanRemoval: true
    )]
    private Collection $videos;

    #[ORM\OneToMany(
        targetEntity: QuizQuestion::class, 
        mappedBy: 'course', 
        cascade: ['persist', 'remove'],  
        orphanRemoval: true
    )]
    private Collection $quizQuestions;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumb = null;

    #[Vich\UploadableField(mapping: 'course_thumbs', fileNameProperty: 'thumb')]
    private ?File $thumbFile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
#[ORM\Column(length: 255, nullable: true)]
private ?string $planPdf = null;

#[Vich\UploadableField(mapping: 'course_plans', fileNameProperty: 'planPdf')]
#[Assert\File(
    maxSize: '10M',
    mimeTypes: ['application/pdf'],
    mimeTypesMessage: 'Veuillez uploader un fichier PDF valide.'
)]
private ?File $planPdfFile = null;

#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'courses')]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
private ?Category $category = null;


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

    public function getSlug(): string
    {
        $slug = (new AsciiSlugger())->slug((string) $this->title)->lower()->toString();

        return $slug !== '' ? $slug : 'course';
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
            $coursePrice->setCourse($this);  
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
            $video->setCourse($this);  
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
            $quizQuestion->setCourse($this);  
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


     public function setThumbFile(?File $file = null): void
    {
        $this->thumbFile = $file;

        if ($file) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getThumbFile(): ?File
    {
        return $this->thumbFile;
    }

    public function getThumb(): ?string
    {
        return $this->thumb;
    }

    public function setThumb(?string $thumb): static
    {
        $this->thumb = $thumb;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function getPlanPdf(): ?string
{
    return $this->planPdf;
}

public function setPlanPdf(?string $planPdf): static
{
    $this->planPdf = $planPdf;
    return $this;
}

public function setPlanPdfFile(?File $file = null): void
{
    $this->planPdfFile = $file;

    if ($file) {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

public function getPlanPdfFile(): ?File
{
    return $this->planPdfFile;
}

public function getCategory(): ?Category
{
    return $this->category;
}

public function setCategory(?Category $category): static
{
    $this->category = $category;
    return $this;
}


}
