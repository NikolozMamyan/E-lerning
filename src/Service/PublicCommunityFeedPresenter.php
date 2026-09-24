<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Comment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PublicCommunityFeedPresenter
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.community_content_sanitizer')]
        private HtmlSanitizerInterface $contentSanitizer,
    ) {
    }

    /** @return array<string, mixed> */
    public function present(Article $article): array
    {
        $author = $article->getAuthor();
        $comments = $article->getComments()->toArray();
        usort($comments, static fn (Comment $left, Comment $right): int => $right->getCreatedAt() <=> $left->getCreatedAt());

        return [
            'id' => $article->getId(),
            'title' => $article->getTitre(),
            'slug' => $article->getSlug(),
            'excerpt' => $this->plainText($article->getDescription(), 240),
            'content' => $this->contentSanitizer->sanitize((string) $article->getDescription()),
            'publishedAt' => $article->getCreatedAt()?->format(DATE_ATOM),
            'author' => [
                'name' => $author?->getUsername() ?: 'Membre de la communauté',
                'avatarUrl' => $author?->getAvatar()
                    ? '/uploads/avatars/'.rawurlencode($author->getAvatar())
                    : '/images/User.svg',
            ],
            'media' => $this->media($article),
            'engagement' => [
                'likes' => $article->getLikesCount(),
                'comments' => $article->getComments()->count(),
            ],
            'latestComments' => array_map(
                fn (Comment $comment): array => $this->presentComment($comment),
                array_slice($comments, 0, 6),
            ),
            'links' => [
                'article' => $this->urlGenerator->generate('app_public_article', [
                    'id' => $article->getId(),
                    'slug' => $article->getSlug(),
                ]),
                'register' => $this->urlGenerator->generate('show_register'),
            ],
        ];
    }

    /** @return array{type: string, url: string}|null */
    private function media(Article $article): ?array
    {
        if ($article->getImage()) {
            return [
                'type' => 'image',
                'url' => '/uploads/articles/'.rawurlencode($article->getImage()),
            ];
        }

        if ($article->getVideo()) {
            return [
                'type' => 'video',
                'url' => '/uploads/videos/'.rawurlencode($article->getVideo()),
            ];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function presentComment(Comment $comment): array
    {
        $author = $comment->getAuthor();

        return [
            'content' => $this->plainText($comment->getContent(), 150),
            'publishedAt' => $comment->getCreatedAt()?->format(DATE_ATOM),
            'likes' => $comment->getLikesCount(),
            'author' => $author?->getUsername() ?: 'Membre de la communauté',
        ];
    }

    private function plainText(?string $value, int $maximumLength): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($value) <= $maximumLength) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maximumLength - 1)).'…';
    }
}
