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
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 64, nullable: true)]
private ?string $apiToken = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $tokenExpiresAt = null;

/**
 * @var Collection<int, Certificate>
 */
#[ORM\OneToMany(targetEntity: Certificate::class, mappedBy: 'passed')]
private Collection $certificates;




public function __construct()
{
    $this->certificates = new ArrayCollection();
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




}
