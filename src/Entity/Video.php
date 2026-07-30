<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VideoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
class Video
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'float')]
    private float $duration; 


    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    private ?Course $course = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_fr = null;

    #[ORM\Column(name: 'scorm_fr', length: 255, nullable: true)]
    private ?string $scormFr = null;

    #[ORM\Column(name: 'scorm_en', length: 255, nullable: true)]
    private ?string $scormEn = null;

    #[ORM\Column(name: 'scorm_de', length: 255, nullable: true)]
    private ?string $scormDe = null;

    #[ORM\Column(name: 'scorm_title_fr', length: 255, nullable: true)]
    private ?string $scormTitleFr = null;

    #[ORM\Column(name: 'scorm_title_en', length: 255, nullable: true)]
    private ?string $scormTitleEn = null;

    #[ORM\Column(name: 'scorm_title_de', length: 255, nullable: true)]
    private ?string $scormTitleDe = null;

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDuration(): float
{
    return $this->duration;
}

public function setDuration(float $duration): self
{
    $this->duration = $duration;
    return $this;
}

    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): self { $this->url = $url; return $this; }

    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): self { $this->course = $course; return $this; }

    public function getUrlFr(): ?string
    {
        return $this->url_fr;
    }

    public function setUrlFr(?string $url_fr): static
    {
        $this->url_fr = $url_fr;

        return $this;
    }

    public function getScormFr(): ?string
    {
        return $this->scormFr;
    }

    public function setScormFr(?string $scormFr): static
    {
        $this->scormFr = $scormFr;

        return $this;
    }

    public function getScormEn(): ?string
    {
        return $this->scormEn;
    }

    public function setScormEn(?string $scormEn): static
    {
        $this->scormEn = $scormEn;

        return $this;
    }

    public function getScormDe(): ?string
    {
        return $this->scormDe;
    }

    public function setScormDe(?string $scormDe): static
    {
        $this->scormDe = $scormDe;

        return $this;
    }

    public function getScormTitleFr(): ?string
    {
        return $this->scormTitleFr;
    }

    public function setScormTitleFr(?string $scormTitleFr): static
    {
        $this->scormTitleFr = $scormTitleFr;

        return $this;
    }

    public function getScormTitleEn(): ?string
    {
        return $this->scormTitleEn;
    }

    public function setScormTitleEn(?string $scormTitleEn): static
    {
        $this->scormTitleEn = $scormTitleEn;

        return $this;
    }

    public function getScormTitleDe(): ?string
    {
        return $this->scormTitleDe;
    }

    public function setScormTitleDe(?string $scormTitleDe): static
    {
        $this->scormTitleDe = $scormTitleDe;

        return $this;
    }
}
