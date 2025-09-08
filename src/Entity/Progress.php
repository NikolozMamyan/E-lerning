<?php

namespace App\Entity;

use App\Repository\ProgressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgressRepository::class)]
class Progress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Video $video = null;

    #[ORM\Column(type: 'integer')]
    private int $watchedSeconds = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $completed = false;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getVideo(): ?Video { return $this->video; }
    public function setVideo(?Video $video): self { $this->video = $video; return $this; }

    public function getWatchedSeconds(): int { return $this->watchedSeconds; }
    public function setWatchedSeconds(int $seconds): self { $this->watchedSeconds = $seconds; return $this; }

    public function isCompleted(): bool { return $this->completed; }
    public function setCompleted(bool $completed): self { $this->completed = $completed; return $this; }
}
