<?php

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
}
