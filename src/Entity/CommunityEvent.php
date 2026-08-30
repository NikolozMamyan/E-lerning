<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommunityEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunityEventRepository::class)]
class CommunityEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 20, max: 10000)]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\GreaterThan('now')]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $location = null;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CommunityEventRegistration> */
    #[ORM\OneToMany(mappedBy: 'communityEvent', targetEntity: CommunityEventRegistration::class)]
    private Collection $registrations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->startsAt = new \DateTimeImmutable('+7 days');
        $this->registrations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(\DateTimeImmutable $startsAt): self { $this->startsAt = $startsAt; return $this; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }
    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, CommunityEventRegistration> */
    public function getRegistrations(): Collection { return $this->registrations; }
}
