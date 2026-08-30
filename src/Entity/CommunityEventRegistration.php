<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommunityEventRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunityEventRegistrationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_community_event_member', fields: ['communityEvent', 'member'])]
class CommunityEventRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CommunityEvent $communityEvent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $member = null;

    #[ORM\Column]
    private \DateTimeImmutable $registeredAt;

    public function __construct()
    {
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCommunityEvent(): ?CommunityEvent { return $this->communityEvent; }
    public function setCommunityEvent(CommunityEvent $communityEvent): self { $this->communityEvent = $communityEvent; return $this; }
    public function getMember(): ?User { return $this->member; }
    public function setMember(User $member): self { $this->member = $member; return $this; }
    public function getRegisteredAt(): \DateTimeImmutable { return $this->registeredAt; }
}
