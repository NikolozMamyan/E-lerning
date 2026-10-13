<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\User;

final readonly class LearnerArticlePresenter
{
    public function __construct(private PublicCommunityFeedPresenter $publicPresenter) {}

    public function present(Article $article, ?User $viewer, bool $allComments = false): array
    {
        $public = $this->publicPresenter->present($article);
        $comments = $article->getComments()->toArray();
        usort($comments, static fn (Comment $left, Comment $right): int => $right->getCreatedAt() <=> $left->getCreatedAt());
        $author = $article->getAuthor();

        return [
            'id' => $article->getId(), 'slug' => $article->getSlug(),
            'title' => $article->getTitre(), 'description' => $public['content'],
            'image' => $article->getImage(), 'video' => $article->getVideo(),
            'createdAt' => $article->getCreatedAt()?->format(DATE_ATOM),
            'author' => ['id' => $author?->getId(), 'username' => $author?->getUsername(), 'avatarUrl' => $author?->getAvatar() ? '/uploads/avatars/'.$author->getAvatar() : null, 'hasPublicProfile' => $author && !in_array('ROLE_ADMIN', $author->getRoles(), true)],
            'likesCount' => $article->getLikesCount(), 'likedByMe' => $viewer ? $article->isLikedByUser($viewer) : false,
            'commentsCount' => $article->getComments()->count(),
            'comments' => array_map(fn (Comment $comment): array => $this->comment($comment, $viewer), $allComments ? $comments : array_slice($comments, 0, 3)),
            'publicUrl' => $public['links']['article'],
        ];
    }

    public function comment(Comment $comment, ?User $viewer): array
    {
        $author = $comment->getAuthor();

        return [
            'id' => $comment->getId(), 'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()?->format(DATE_ATOM),
            'likesCount' => $comment->getLikesCount(), 'likedByMe' => $viewer ? $comment->isLikedByUser($viewer) : false,
            'author' => ['id' => $author?->getId(), 'username' => $author?->getUsername(), 'display' => $author?->getUsername(), 'avatar' => $author ? mb_strtoupper(mb_substr((string) $author->getUsername(), 0, 1)) : null, 'avatarUrl' => $author?->getAvatar() ? '/uploads/avatars/'.$author->getAvatar() : null, 'hasPublicProfile' => $author && !in_array('ROLE_ADMIN', $author->getRoles(), true)],
            'articleId' => $comment->getArticle()?->getId(),
        ];
    }
}
