<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Notification;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Repository\NotificationRepository;
use App\Service\LinkPreviewService;
use App\Service\MailerService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/articles')]
class ArticleApiController extends AbstractController
{
    // ------------------------------------------------------------
    // GET /api/articles?limit=5&offset=0
    // ------------------------------------------------------------
#[Route('', name: 'api_articles_list', methods: ['GET'])]
public function list(Request $request, ArticleRepository $repo): JsonResponse
{
    $limit = max(1, min(50, (int) $request->query->get('limit', 5)));
    $offset = max(0, (int) $request->query->get('offset', 0));

    // items paginés
    $articles = $repo->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
    $items = array_map(fn(Article $a) => $this->serializeArticle($a), $articles);

    // ✅ total global
    $total = $repo->count([]);

    return $this->json([
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($items),   // nb d'items renvoyés dans cette page
        'total' => $total,          // ✅ nb total en base
        'pages' => (int) ceil($total / $limit), // pratique côté front
        'items' => $items,
        'hasMore' => ($offset + $limit) < $total, // optionnel mais super utile
    ]);
}

    // ------------------------------------------------------------
    // POST /api/articles  (multipart/form-data)
    // fields: title, description, image?, video?
    // ------------------------------------------------------------
    #[Route('', name: 'api_articles_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        MailerService $mailer
    ): JsonResponse {
        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));

        if ($title === '' || $description === '') {
            return $this->json(['error' => 'Title and description are required'], 400);
        }

        $existing = $em->getRepository(Article::class)->findOneBy(['titre' => $title]);
        if ($existing) {
            return $this->json(['error' => 'An article with a similar title already exists'], 409);
        }

        $imageFile = $request->files->get('image');
        $videoFile = $request->files->get('video');

        if ($imageFile && $videoFile) {
            return $this->json(['error' => 'You cannot upload both an image and a video'], 400);
        }

        $article = new Article();
        $article->setTitre($title);
        $article->setDescription($description);
        $article->setAuthor($this->getUser());

        // image
        if ($imageFile) {
            $newName = uniqid() . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('articles_dir'), $newName);
                $article->setImage($newName);
            } catch (FileException $e) {
                return $this->json(['error' => 'Image upload error'], 500);
            }
        }

        // video
        if ($videoFile) {
            $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

            if (!in_array($videoFile->getMimeType(), $allowedMimeTypes, true)) {
                return $this->json(['error' => 'Invalid video format'], 400);
            }

            if ($videoFile->getSize() > 50 * 1024 * 1024) {
                return $this->json(['error' => 'Video is too large (max 50MB)'], 400);
            }

            $videoName = uniqid('video_') . '.' . $videoFile->guessExtension();

            try {
                $videoFile->move($this->getParameter('videos_dir'), $videoName);
                $article->setVideo($videoName);
            } catch (FileException $e) {
                return $this->json(['error' => 'Video upload error'], 500);
            }
        }

        $em->persist($article);
        $em->flush();

        // notif user
        $notificationService->createEntityNotification(
            user: $this->getUser(),
            title: "Your article has been published!",
            relatedEntity: $article,
            message: "Your article {$article->getTitre()} is now visible in the feed.",
            type: "success",
            actionUrl: $this->generateUrl('app_public_article', ['id' => $article->getId(), 'slug' => $article->getSlug()]),
            icon: "fa-solid fa-newspaper"
        );

        // mail admin
        $mailer->send(
            to: "pruffin@les-consultants.lu",
            subject: "New article published on your platform",
            template: "emails/new_article.html.twig",
            context: [
                "author" => $this->getUser(),
                "article" => $article,
                "description" => $article->getDescription(),
            ]
        );

        return $this->json([
            'success' => true,
            'item' => $this->serializeArticle($article),
        ], 201);
    }

    // ------------------------------------------------------------
    // PATCH /api/articles/{id}  (JSON)  (ou POST multipart si tu veux changer media)
    // body: { "title": "...", "description": "...", "remove_image": true, "remove_video": true }
    // + optional multipart route below for media
    // ------------------------------------------------------------
    #[Route('/{id<\d+>}', name: 'api_articles_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(
        Article $article,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($this->getUser() !== $article->getAuthor()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $payload = $request->toArray();

        $title = isset($payload['title']) ? trim((string)$payload['title']) : null;
        $description = isset($payload['description']) ? trim((string)$payload['description']) : null;

        if ($title !== null && $title === '') return $this->json(['error' => 'Title cannot be empty'], 400);
        if ($description !== null && $description === '') return $this->json(['error' => 'Description cannot be empty'], 400);

        if ($title !== null) $article->setTitre($title);
        if ($description !== null) $article->setDescription($description);

        // remove flags
        if (!empty($payload['remove_image'])) {
            $this->deleteArticleImageIfExists($article);
            $article->setImage(null);
        }
        if (!empty($payload['remove_video'])) {
            $this->deleteArticleVideoIfExists($article);
            $article->setVideo(null);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'item' => $this->serializeArticle($article),
        ]);
    }

    // ------------------------------------------------------------
    // POST /api/articles/{id}/media  (multipart/form-data)
    // fields: image? OR video? (mutuel)
    // ------------------------------------------------------------
    #[Route('/{id<\d+>}/media', name: 'api_articles_update_media', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateMedia(
        Article $article,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($this->getUser() !== $article->getAuthor()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $imageFile = $request->files->get('image');
        $videoFile = $request->files->get('video');

        if (!$imageFile && !$videoFile) {
            return $this->json(['error' => 'Provide image or video'], 400);
        }
        if ($imageFile && $videoFile) {
            return $this->json(['error' => 'You cannot upload both an image and a video'], 400);
        }

        if ($imageFile) {
            $this->deleteArticleImageIfExists($article);
            $this->deleteArticleVideoIfExists($article);
            $article->setVideo(null);

            $newFilename = uniqid() . '_' . $imageFile->getClientOriginalName();
            try {
                $imageFile->move($this->getParameter('articles_dir'), $newFilename);
                $article->setImage($newFilename);
            } catch (FileException $e) {
                return $this->json(['error' => 'Image upload error'], 500);
            }
        }

        if ($videoFile) {
            $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

            if (!in_array($videoFile->getMimeType(), $allowedMimeTypes, true)) {
                return $this->json(['error' => 'Invalid video format'], 400);
            }
            if ($videoFile->getSize() > 50 * 1024 * 1024) {
                return $this->json(['error' => 'Video is too large (max 50MB)'], 400);
            }

            $this->deleteArticleVideoIfExists($article);
            $this->deleteArticleImageIfExists($article);
            $article->setImage(null);

            $videoName = uniqid('video_') . '.' . $videoFile->guessExtension();
            try {
                $videoFile->move($this->getParameter('videos_dir'), $videoName);
                $article->setVideo($videoName);
            } catch (FileException $e) {
                return $this->json(['error' => 'Video upload error'], 500);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'item' => $this->serializeArticle($article),
        ]);
    }

    // ------------------------------------------------------------
    // POST /api/articles/{id}/like
    // ------------------------------------------------------------
    #[Route('/{id<\d+>}/like', name: 'api_articles_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(
        Article $article,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): JsonResponse {
        $user = $this->getUser();
        $author = $article->getAuthor();

        if ($article->isLikedByUser($user)) {
            $article->removeLikedBy($user);
            $liked = false;
        } else {
            $article->addLikedBy($user);
            $liked = true;

            if ($author !== $user) {
                $notificationService->createEntityNotification(
                    user: $author,
                    title: $user->getUsername() . " liked your article",
                    relatedEntity: $article,
                    message: $user->getUsername() . " reacted to " . $article->getTitre() . ".",
                    type: "info",
                    actionUrl: $this->generateUrl('app_public_article', ['id' => $article->getId(), 'slug' => $article->getSlug()]),
                    icon: "fa-solid fa-heart",
                    priority: "normal"
                );
            }
        }

        $em->flush();

        return $this->json([
            'liked' => $liked,
            'likes' => $article->getLikesCount(),
        ]);
    }

    // ------------------------------------------------------------
    // POST /api/articles/{id}/comments
    // body JSON: { "content": "..." }
    // ------------------------------------------------------------
    #[Route('/{id<\d+>}/comments', name: 'api_articles_add_comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addComment(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): JsonResponse {
        $payload = $request->toArray();
        $content = trim((string)($payload['content'] ?? ''));

        if ($content === '') {
            return $this->json(['error' => 'Comment cannot be empty'], 400);
        }
        if (mb_strlen($content) > 220) {
            return $this->json(['error' => 'Comment is too long'], 400);
        }

        $user = $this->getUser();
        $author = $article->getAuthor();

        $comment = new Comment();
        $comment->setContent($content);
        $comment->setArticle($article);
        $comment->setAuthor($user);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $em->persist($comment);
        $em->flush();

        if ($author !== $user) {
            $notificationService->createEntityNotification(
                user: $author,
                title: $user->getUsername() . " commented on your article",
                message: $user->getUsername() . " left a comment on " . $article->getTitre() . ".",
                relatedEntity: $article,
                type: "info",
                icon: "fa-solid fa-comment",
                actionUrl: $this->generateUrl('app_public_article', ['id' => $article->getId(), 'slug' => $article->getSlug()]),
            );
        }

        return $this->json([
            'success' => true,
            'item' => $this->serializeComment($comment),
        ], 201);
    }

    // ------------------------------------------------------------
    // PATCH /api/articles/comments/{id}
    // body JSON: { "content": "..." }
    // ------------------------------------------------------------
    #[Route('/comments/{id<\d+>}', name: 'api_comments_edit', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function editComment(
        Comment $comment,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($comment->getAuthor() !== $this->getUser()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $payload = $request->toArray();
        $content = trim((string)($payload['content'] ?? ''));

        if ($content === '') {
            return $this->json(['error' => 'Content cannot be empty'], 400);
        }
        if (mb_strlen($content) > 220) {
            return $this->json(['error' => 'Comment is too long'], 400);
        }

        $comment->setContent($content);
        $em->flush();

        return $this->json([
            'success' => true,
            'item' => $this->serializeComment($comment),
        ]);
    }

    // ------------------------------------------------------------
    // DELETE /api/articles/comments/{id}
    // ------------------------------------------------------------
    #[Route('/comments/{id<\d+>}', name: 'api_comments_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteComment(Comment $comment, EntityManagerInterface $em): JsonResponse
    {
        if ($comment->getAuthor() !== $this->getUser()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $em->remove($comment);
        $em->flush();

        return $this->json(['success' => true]);
    }

    // ------------------------------------------------------------
    // POST /api/articles/comments/{id}/like
    // ------------------------------------------------------------
    #[Route('/comments/{id<\d+>}/like', name: 'api_comments_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function likeComment(
        Comment $comment,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        NotificationRepository $notificationRepo
    ): JsonResponse {
        $user = $this->getUser();

        if ($comment->isLikedByUser($user)) {
            $comment->removeLikedBy($user);
            $em->flush();

            return $this->json([
                'liked' => false,
                'count' => $comment->getLikesCount(),
            ]);
        }

        $comment->addLikedBy($user);
        $em->flush();

        // notification (si pas déjà)
        try {
            $commentAuthor = $comment->getAuthor();
            if ($commentAuthor && $commentAuthor->getId() !== $user->getId()) {
                $alreadyNotified = $notificationRepo->findOneBy([
                    'user' => $commentAuthor,
                    'relatedEntityId' => $comment->getId(),
                    'relatedEntityType' => get_class($comment),
                    'icon' => 'comment-like',
                ]);

                if (!$alreadyNotified) {
                    $notificationService->createEntityNotification(
                        $commentAuthor,
                        '❤️ Nouveau like',
                        $comment,
                        'Vous avez reçu un like sur votre commentaire.',
                        Notification::TYPE_INFO,
                        $comment->getArticle() ? $this->generateUrl('app_public_article', [
                            'id' => $comment->getArticle()->getId(),
                            'slug' => $comment->getArticle()->getSlug(),
                        ]) : null,
                        'comment-like',
                        Notification::PRIORITY_NORMAL
                    );
                }
            }
        } catch (\Throwable $e) {
            // pas bloquant
        }

        return $this->json([
            'liked' => true,
            'count' => $comment->getLikesCount(),
        ]);
    }

    // ------------------------------------------------------------
    // DELETE /api/articles/{id}
    // ------------------------------------------------------------
    #[Route('/{id<\d+>}', name: 'api_articles_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Article $article, EntityManagerInterface $em): JsonResponse
    {
        if ($this->getUser() !== $article->getAuthor()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $this->deleteArticleImageIfExists($article);
        $this->deleteArticleVideoIfExists($article);

        $em->remove($article);
        $em->flush();

        return $this->json(['success' => true]);
    }

    // ------------------------------------------------------------
    // POST /api/articles/link-preview
    // body JSON: { "url": "https://..." }
    // ------------------------------------------------------------
    #[Route('/link-preview', name: 'api_link_preview', methods: ['POST'])]
    public function linkPreview(Request $request, LinkPreviewService $previewService): JsonResponse
    {
        $payload = $request->toArray();
        $url = $payload['url'] ?? null;

        if (!$url) {
            return $this->json(['error' => 'URL manquante'], 400);
        }

        try {
            return $this->json($previewService->fetch($url));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Impossible de charger le preview'], 500);
        }
    }

    // =========================
    // Helpers
    // =========================

private function serializeArticle(Article $a): array
{
    $user = $this->getUser();

    // Option : trier les comments en mémoire (si pas déjà triés en DB)
    $comments = $a->getComments()->toArray();

    usort($comments, function($c1, $c2) {
        return ($c2->getCreatedAt() <=> $c1->getCreatedAt());
    });

    // On limite à 3 (ou ce que tu veux)
    $comments = array_slice($comments, 0, 3);

    return [
        'id' => $a->getId(),
        'title' => $a->getTitre(),
        'description' => $a->getDescription(),
        'image' => $a->getImage(),
        'video' => $a->getVideo(),
        'createdAt' => $a->getCreatedAt()?->format(DATE_ATOM),
        'author' => [
            'id' => $a->getAuthor()?->getId(),
            'username' => $a->getAuthor()?->getUserIdentifier(),
        ],
        'likesCount' => $a->getLikesCount(),
        'likedByMe' => $user ? $a->isLikedByUser($user) : false,

        // ✅ Ajouts
        'commentsCount' => $a->getComments()->count(),
        'comments' => array_map(fn(Comment $c) => $this->serializeComment($c), $comments),
    ];
}
    private function serializeComment(Comment $c): array
    {
        $u = $c->getAuthor();

        return [
            'id' => $c->getId(),
            'content' => $c->getContent(),
            'createdAt' => $c->getCreatedAt()?->format(DATE_ATOM),
            'likesCount' => $c->getLikesCount(),
            'author' => [
                'id' => $u?->getId(),
                'display' => $u ? ('User-' . $c->getCreatedAt()->format('Ymd') . '-' . $u->getId()) : null,
                'avatar' => $u ? strtoupper($u->getUserIdentifier()[0]) : null,
            ],
            'articleId' => $c->getArticle()?->getId(),
        ];
    }

    private function deleteArticleImageIfExists(Article $article): void
    {
        if ($article->getImage()) {
            $path = $this->getParameter('articles_dir') . '/' . $article->getImage();
            if (is_file($path)) @unlink($path);
        }
    }

    private function deleteArticleVideoIfExists(Article $article): void
    {
        if ($article->getVideo()) {
            $path = $this->getParameter('videos_dir') . '/' . $article->getVideo();
            if (is_file($path)) @unlink($path);
        }
    }
}
