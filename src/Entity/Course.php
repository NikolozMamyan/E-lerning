<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Video::class, cascade: ['persist', 'remove'])]
    private Collection $videos;

    public function __construct()
    {
        $this->videos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    /** @return Collection<int, Video> */
    public function getVideos(): Collection { return $this->videos; }
    public function addVideo(Video $video): self
    {
        if (!$this->videos->contains($video)) {
            $this->videos[] = $video;
            $video->setCourse($this);
        }
        return $this;
    }
    public function removeVideo(Video $video): self
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getCourse() === $this) {
                $video->setCourse(null);
            }
        }
        return $this;
    }
    public function isUnlockedForUser(?User $user): bool
{
    if (!$user) return false;
    foreach ($user->getEnrollments() as $enrollment) {
        if ($enrollment->getCourse() === $this) {
            return true;
        }
    }
    return false;
}

}
