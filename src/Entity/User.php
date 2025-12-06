<?php

namespace App\Entity;

use App\Entity\Character;
use App\Entity\Friendship;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

        #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $password = null;


    #[ORM\Column(length: 64, nullable: true)]
    private ?string $apiToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $masterDegree = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bachelorDegree = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

        #[ORM\OneToMany(mappedBy: 'company', targetEntity: Collaboration::class, orphanRemoval: true)]
    private Collection $collaborationsAsCompany;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: Collaboration::class, orphanRemoval: true)]
    private Collection $collaborationsAsEmployee;


#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $tokenExpiresAt = null;

/**
 * @var Collection<int, Certificate>
 */
#[ORM\OneToMany(targetEntity: Certificate::class, mappedBy: 'passed')]
private Collection $certificates;

#[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
private Collection $notifications;


#[ORM\OneToMany(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist', 'remove'])]
private Collection $subscriptions;

#[ORM\OneToMany(mappedBy: 'author', targetEntity: Article::class)]
private Collection $articles;

#[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class)]
private Collection $comments;


public function __construct()
{
        $this->certificates = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->collaborationsAsCompany = new ArrayCollection();
        $this->collaborationsAsEmployee = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
    public function getApiToken(): ?string
{
    return $this->apiToken;
}

public function setApiToken(?string $apiToken): static
{
    $this->apiToken = $apiToken;

    return $this;
}
public function getTokenExpiresAt(): ?\DateTimeInterface
{
    return $this->tokenExpiresAt;
}

public function setTokenExpiresAt(?\DateTimeInterface $expiresAt): static
{
    $this->tokenExpiresAt = $expiresAt;
    return $this;
}

public function getUsername(): string
{
    return $this->username;
}

public function setUsername(string $username): self
{
    $this->username = $username;

    return $this;
}
/**
 * @return Collection<int, Certificate>
 */
public function getCertificates(): Collection
{
    return $this->certificates;
}

public function addCertificate(Certificate $certificate): static
{
    if (!$this->certificates->contains($certificate)) {
        $this->certificates->add($certificate);
        $certificate->setPassed($this);
    }

    return $this;
}

public function removeCertificate(Certificate $certificate): static
{
    if ($this->certificates->removeElement($certificate)) {
        // set the owning side to null (unless already changed)
        if ($certificate->getPassed() === $this) {
            $certificate->setPassed(null);
        }
    }

    return $this;
}
public function getCompanyNameFromEmail(): string
{
    if (!$this->email) {
        return 'Saranco.';
    }

    // Récupère le domaine après le @
    $parts = explode('@', $this->email);
    $domain = $parts[1] ?? '';

    // Enlève l’extension (.com, .fr, etc.)
    $domainParts = explode('.', $domain);
    $companyRaw = $domainParts[0] ?? '';

    // Mets en forme : "xyzbank" → "XYZ Bank"
    $company = preg_replace('/([a-z])([A-Z])/', '$1 $2', ucfirst($companyRaw));

    return $company ?: 'Saranco.';
}

/**
 * @return Collection<int, Notification>
 */
public function getNotifications(): Collection
{
    return $this->notifications;
}

public function addNotification(Notification $notification): static
{
    if (!$this->notifications->contains($notification)) {
        $this->notifications->add($notification);
        $notification->setUser($this);
    }

    return $this;
}

public function removeNotification(Notification $notification): static
{
    if ($this->notifications->removeElement($notification)) {
        // set the owning side to null (unless already changed)
        if ($notification->getUser() === $this) {
            $notification->setUser(null);
        }
    }

    return $this;
}

public function getUnreadNotificationsCount(): int
{
    return $this->notifications->filter(function($notification) {
        return !$notification->getIsRead();
    })->count();
}

public function getUnreadNotifications(): Collection
{
    return $this->notifications->filter(function($notification) {
        return !$notification->getIsRead();
    });
}


public function getPhone(): ?string
{
    return $this->phone;
}

public function setPhone(?string $phone): self
{
    $this->phone = $phone;

    return $this;
}

public function getBio(): ?string
{
    return $this->bio;
}

public function setBio(?string $bio): self
{
    $this->bio = $bio;

    return $this;
}

public function getCountry(): ?string
{
    return $this->country;
}

public function setCountry(?string $country): self
{
    $this->country = $country;

    return $this;
}

public function getCity(): ?string
{
    return $this->city;
}

public function setCity(?string $city): self
{
    $this->city = $city;

    return $this;
}

public function getPostalCode(): ?string
{
    return $this->postalCode;
}

public function setPostalCode(?string $postalCode): self
{
    $this->postalCode = $postalCode;

    return $this;
}

public function getTaxId(): ?string
{
    return $this->taxId;
}

public function setTaxId(?string $taxId): self
{
    $this->taxId = $taxId;

    return $this;
}

public function getMasterDegree(): ?string
{
    return $this->masterDegree;
}

public function setMasterDegree(?string $masterDegree): self
{
    $this->masterDegree = $masterDegree;

    return $this;
}

public function getBachelorDegree(): ?string
{
    return $this->bachelorDegree;
}

public function setBachelorDegree(?string $bachelorDegree): self
{
    $this->bachelorDegree = $bachelorDegree;

    return $this;
}
public function getAvatar(): ?string
{
    return $this->avatar;
}

public function setAvatar(?string $avatar): self
{
    $this->avatar = $avatar;

    return $this;
}

 /**
     * @return Collection<int, Collaboration>
     */
    public function getCollaborationsAsCompany(): Collection
    {
        return $this->collaborationsAsCompany;
    }

    public function addCollaborationAsCompany(Collaboration $collaboration): self
    {
        if (!$this->collaborationsAsCompany->contains($collaboration)) {
            $this->collaborationsAsCompany->add($collaboration);
            $collaboration->setCompany($this);
        }

        return $this;
    }

    public function removeCollaborationAsCompany(Collaboration $collaboration): self
    {
        if ($this->collaborationsAsCompany->removeElement($collaboration)) {
            if ($collaboration->getCompany() === $this) {
                $collaboration->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Collaboration>
     */
    public function getCollaborationsAsEmployee(): Collection
    {
        return $this->collaborationsAsEmployee;
    }

    public function addCollaborationAsEmployee(Collaboration $collaboration): self
    {
        if (!$this->collaborationsAsEmployee->contains($collaboration)) {
            $this->collaborationsAsEmployee->add($collaboration);
            $collaboration->setEmployee($this);
        }

        return $this;
    }

    public function removeCollaborationAsEmployee(Collaboration $collaboration): self
    {
        if ($this->collaborationsAsEmployee->removeElement($collaboration)) {
            if ($collaboration->getEmployee() === $this) {
                $collaboration->setEmployee(null);
            }
        }

        return $this;
    }

    public function getSubscriptions(): Collection
{
    return $this->subscriptions;
}

public function addSubscription(Subscription $subscription): static
{
    if (!$this->subscriptions->contains($subscription)) {
        $this->subscriptions->add($subscription);
        $subscription->setUser($this);
    }
    return $this;
}

public function removeSubscription(Subscription $subscription): static
{
    if ($this->subscriptions->removeElement($subscription)) {
        if ($subscription->getUser() === $this) {
            $subscription->setUser(null);
        }
    }
    return $this;
}

public function hasActiveSubscription(): bool
{
    foreach ($this->subscriptions as $subscription) {
        if ($subscription->isActive()) {
            return true;
        }
    }
    return false;
}

public function getArticles(): Collection
{
    return $this->articles;
}

public function addArticle(Article $article): static
{
    if (!$this->articles->contains($article)) {
        $this->articles->add($article);
        $article->setAuthor($this);
    }

    return $this;
}

public function removeArticle(Article $article): static
{
    if ($this->articles->removeElement($article)) {
        if ($article->getAuthor() === $this) {
            $article->setAuthor(null);
        }
    }

    return $this;
}
public function getComments(): Collection
{
    return $this->comments;
}

public function addComment(Comment $comment): static
{
    if (!$this->comments->contains($comment)) {
        $this->comments->add($comment);
        $comment->setAuthor($this);
    }

    return $this;
}

public function removeComment(Comment $comment): static
{
    if ($this->comments->removeElement($comment)) {
        if ($comment->getAuthor() === $this) {
            $comment->setAuthor(null);
        }
    }

    return $this;
}

}
