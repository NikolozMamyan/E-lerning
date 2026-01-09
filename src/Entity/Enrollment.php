<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
class Enrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Course $course = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(length: 50, nullable: true)]
private ?string $invoiceNumber = null;

#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
private ?\DateTimeInterface $invoiceDate = null;

#[ORM\Column(nullable: true)]
private ?int $invoiceAmountCents = null; // en centimes

#[ORM\Column(length: 3, nullable: true)]
private ?string $invoiceCurrency = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): self { $this->course = $course; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
public function setInvoiceNumber(?string $invoiceNumber): self { $this->invoiceNumber = $invoiceNumber; return $this; }

public function getInvoiceDate(): ?\DateTimeInterface { return $this->invoiceDate; }
public function setInvoiceDate(?\DateTimeInterface $invoiceDate): self { $this->invoiceDate = $invoiceDate; return $this; }

public function getInvoiceAmountCents(): ?int { return $this->invoiceAmountCents; }
public function setInvoiceAmountCents(?int $cents): self { $this->invoiceAmountCents = $cents; return $this; }

public function getInvoiceCurrency(): ?string { return $this->invoiceCurrency; }
public function setInvoiceCurrency(?string $currency): self { $this->invoiceCurrency = $currency; return $this; }
}
