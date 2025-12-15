<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;
    

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;


    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $likedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: Comment::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $comments;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $video = null;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->likedBy = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        $this->slug = $this->slugify($titre);
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setArticle($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getArticle() === $this) {
                $comment->setArticle(null);
            }
        }

        return $this;
    }

    public function isLikedByUser(User $user): bool
{
    return $this->likedBy->contains($user);
}

public function addLikedBy(User $user): static
{
    if (!$this->likedBy->contains($user)) {
        $this->likedBy->add($user);
    }
    return $this;
}

public function removeLikedBy(User $user): static
{
    $this->likedBy->removeElement($user);
    return $this;
}

public function getLikesCount(): int
{
    return $this->likedBy->count();
}
public function getVideo(): ?string
{
    return $this->video;
}

public function setVideo(?string $video): self
{
    $this->video = $video;
    return $this;
}
private function slugify(string $text): string
{
    // Supprimer les emojis et symboles spéciaux qui cassent iconv
    $text = preg_replace('/[\x{1F600}-\x{1F6FF}]/u', '', $text); // emojis
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text); // autres symboles

    $text = strtolower($text);

    // Utiliser transliterator si disponible (meilleur choix)
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; Lower()', 
            $text
        );
    } else {
        // Fallback si transliterator n'est pas installé
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    // Remplacer tout ce qui n'est pas a-z/0-9 par des tirets
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');
}
}
