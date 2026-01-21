<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Notification;
use App\Service\MailerService;
use App\Service\LinkPreviewService;
use App\Service\NotificationService;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/app/articles')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'article_feed', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
    $limit = 5;
    $offset = 0;

    $articles = $articleRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

    return $this->render('article/feed.html.twig', [
        'articles' => $articles,
        'limit' => $limit,
        'offset' => $offset,
    ]);
    }
    #[Route('/load-more', name: 'article_feed_load_more', methods: ['GET'])]
public function loadMore(Request $request, ArticleRepository $articleRepository): Response
{
    $limit = (int) $request->query->get('limit', 5);
    $offset = (int) $request->query->get('offset', 0);

    $articles = $articleRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

    return $this->render('components/feed/_articles_chunk.html.twig', [
        'articles' => $articles
    ]);
}


    #[Route('/create', name: 'article_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em,  NotificationService $notificationService,
    MailerService $mailer): Response
    {
        $title = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));

        
        if (empty($title) || empty($description)) {
            $this->addFlash('error', 'Title and content are required');
            return $this->redirectToRoute('article_feed');
        }

        // Check for duplicate title
$existing = $em->getRepository(Article::class)->findOneBy(['titre' => $title]);

if ($existing) {
    $this->addFlash('error', 'An article with a similar title already exists.');
    return $this->redirectToRoute('article_feed');
}

        $article = new Article();
        $article->setTitre($title);
        $article->setDescription($description);
        $article->setAuthor($this->getUser());

        // Image upload
        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $newName = uniqid() . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('articles_dir'), $newName);
                $article->setImage($newName);
            } catch (FileException $e) {
                $this->addFlash('error', 'Image upload error');
            }
        }
        // Video upload
$videoFile = $request->files->get('video');

if ($videoFile) {
    $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

    if (!in_array($videoFile->getMimeType(), $allowedMimeTypes)) {
        $this->addFlash('error', 'Invalid video format');
        return $this->redirectToRoute('article_feed');
    }

    // Optionnel : limite taille (ex 50MB)
    if ($videoFile->getSize() > 50 * 1024 * 1024) {
        $this->addFlash('error', 'Video is too large (max 50MB)');
        return $this->redirectToRoute('article_feed');
    }

    $videoName = uniqid('video_') . '.' . $videoFile->guessExtension();

    try {
        $videoFile->move(
            $this->getParameter('videos_dir'),
            $videoName
        );
        $article->setVideo($videoName);
    } catch (FileException $e) {
        $this->addFlash('error', 'Video upload error');
    }
}
        $em->persist($article);
        $em->flush();

        // -------------------------------------------------------
    // ✅ 1. Création de la notification utilisateur
    // -------------------------------------------------------
    $notificationService->createEntityNotification(
        user: $this->getUser(),
        title: "Your article has been published!",
        relatedEntity: $article,
        message: "Your article {$article->getTitre()} is now visible in the feed.",
        type: "success",
        actionUrl: "/app/articles#article-" . $article->getId(),
        icon: "fa-solid fa-newspaper"
    );

    // -------------------------------------------------------
    // ✅ 2. Envoi d’email à l’administrateur
    // -------------------------------------------------------
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

    // -------------------------------------------------------
    // Message flash visuel en bas à droite
    // -------------------------------------------------------
    $this->addFlash('success', 'Article published successfully!');
        return $this->redirectToRoute('article_feed');
    }


#[Route('/{id}/like', name: 'article_like', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function like(
    Article $article,
    EntityManagerInterface $em,
    NotificationService $notificationService
): Response {
    $user = $this->getUser();
    $author = $article->getAuthor();

    // Toggle like
    if ($article->isLikedByUser($user)) {
        $article->removeLikedBy($user);
        $liked = false;
    } else {
        $article->addLikedBy($user);
        $liked = true;

        // ---------------------------------------------------
        // ✅ Créer une notification pour l'auteur de l'article
        // ---------------------------------------------------
        if ($author !== $user) { // éviter auto-notif
            $notificationService->createEntityNotification(
                user: $author,
                title: "A user liked your article",
                relatedEntity: $article,
                message: "Your article {$article->getTitre()} received a new like.",
                type: "info",
                actionUrl: "/app/articles#article-" . $article->getId(),
                icon: "fa-solid fa-heart",
                priority: "normal"
            );
        }
    }

    $em->flush();

    return $this->json([
        'liked' => $liked,
        'likes' => $article->getLikesCount()
    ]);
}

#[Route('/comments/{id}/edit', name: 'comment_edit', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function editComment(
    Comment $comment,
    Request $request,
    EntityManagerInterface $em
): JsonResponse {
    if ($comment->getAuthor() !== $this->getUser()) {
        return $this->json(['error' => 'Unauthorized'], 403);
    }

    $content = trim($request->request->get('content', ''));
    if (!$content) {
        return $this->json(['error' => 'Content cannot be empty'], 400);
    }

    $comment->setContent($content);
    $em->flush();

    return $this->json([
        'success' => true,
        'content' => $comment->getContent()
    ]);
}

#[Route('/comments/{id}/delete', name: 'comment_delete', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function deleteComment(
    Comment $comment,
    EntityManagerInterface $em
): JsonResponse {
    if ($comment->getAuthor() !== $this->getUser()) {
        return $this->json(['error' => 'Unauthorized'], 403);
    }

    $em->remove($comment);
    $em->flush();

    return $this->json(['success' => true]);
}


#[Route('/{id}/comment', name: 'article_comment', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function addComment(
    Article $article,
    Request $request,
    EntityManagerInterface $em,
    NotificationService $notificationService
): Response {
    $content = trim($request->request->get('comment', ''));

if (empty($content)) {
    return $this->json(['error' => 'Comment cannot be empty'], 400);
}
if (mb_strlen($content) > 220) {
    return $this->json(['error' => 'Comment is too long'], 400);
}


    $user = $this->getUser();
    $author = $article->getAuthor();

    // Création du commentaire
    $comment = new Comment();
    $comment->setContent($content);
    $comment->setArticle($article);
    $comment->setAuthor($user);
    $comment->setCreatedAt(new \DateTimeImmutable());

    $em->persist($comment);
    $em->flush();

    // ---------------------------------------------------
    // ✅ NOTIFICATION ANONYME POUR L'AUTEUR
    // ---------------------------------------------------
    if ($author !== $user) {
        $notificationService->createEntityNotification(
            user: $author,
            title: "A user commented on your article",
            message: "Someone left a new comment on {$article->getTitre()}.",
            relatedEntity: $article,
            type: "info",
            icon: "fa-solid fa-comment",
            actionUrl: "/app/articles#article-" . $article->getId(),
        );
    }
    return $this->json([
        'id' => $comment->getId(),
        'author' => "User-" . $comment->getCreatedAt()->format('Ymd') . "-" . $user->getId(),
        'avatar' => strtoupper($user->getUsername()[0]),
        'content' => $comment->getContent(),
        'date' => $comment->getCreatedAt()->format('m/d/Y')
    ]);
}



#[Route('/{id}/edit', name: 'article_edit')]
#[IsGranted('ROLE_USER')]
public function edit(Article $article, Request $request, EntityManagerInterface $em): Response
{
    if ($this->getUser() !== $article->getAuthor()) {
        throw $this->createAccessDeniedException('You are not allowed to edit this article');
    }

    if ($request->isMethod('POST')) {

        // ---- Titre + description ----
        $title = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));

        if (empty($title) || empty($description)) {
            $this->addFlash('error', 'Title and content are required');
            return $this->redirectToRoute('article_edit', ['id' => $article->getId()]);
        }

        $article->setTitre($title);
        $article->setDescription($description);

        // ---- GESTION IMAGE/VIDEO MUTUELLE ----
        $imageFile = $request->files->get('image');
        $videoFile = $request->files->get('video');

        // Vérifier qu'on n'a pas les deux en même temps
        if ($imageFile && $videoFile) {
            $this->addFlash('error', 'You cannot upload both an image and a video');
            return $this->redirectToRoute('article_edit', ['id' => $article->getId()]);
        }

        // ---- IMAGE UPLOAD ----
        if ($imageFile) {
            // Supprimer l'ancienne image si elle existe
            if ($article->getImage()) {
                $oldImagePath = $this->getParameter('articles_dir') . '/' . $article->getImage();
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Supprimer la vidéo si elle existe (car on met une image)
            if ($article->getVideo()) {
                $oldVideoPath = $this->getParameter('videos_dir') . '/' . $article->getVideo();
                if (file_exists($oldVideoPath)) {
                    unlink($oldVideoPath);
                }
                $article->setVideo(null);
            }

            $newFilename = uniqid() . '_' . $imageFile->getClientOriginalName();
            $imageFile->move(
                $this->getParameter('articles_dir'),
                $newFilename
            );

            $article->setImage($newFilename);
        }

        // ---- VIDEO UPLOAD ----
        if ($videoFile) {
            $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

            if (!in_array($videoFile->getMimeType(), $allowedMimeTypes)) {
                $this->addFlash('error', 'Invalid video format');
                return $this->redirectToRoute('article_edit', ['id' => $article->getId()]);
            }

            if ($videoFile->getSize() > 50 * 1024 * 1024) {
                $this->addFlash('error', 'Video is too large (max 50MB)');
                return $this->redirectToRoute('article_edit', ['id' => $article->getId()]);
            }

            // Supprimer l'ancienne vidéo si elle existe
            if ($article->getVideo()) {
                $oldVideoPath = $this->getParameter('videos_dir') . '/' . $article->getVideo();
                if (file_exists($oldVideoPath)) {
                    unlink($oldVideoPath);
                }
            }

            // Supprimer l'image si elle existe (car on met une vidéo)
            if ($article->getImage()) {
                $oldImagePath = $this->getParameter('articles_dir') . '/' . $article->getImage();
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                $article->setImage(null);
            }

            $videoName = uniqid('video_') . '.' . $videoFile->guessExtension();

            try {
                $videoFile->move(
                    $this->getParameter('videos_dir'),
                    $videoName
                );
                $article->setVideo($videoName);
            } catch (FileException $e) {
                $this->addFlash('error', 'Video upload error');
            }
        }

        // ---- SUPPRESSION MANUELLE (si checkbox cochée) ----
        if ($request->request->get('remove_image') === '1') {
            if ($article->getImage()) {
                $oldImagePath = $this->getParameter('articles_dir') . '/' . $article->getImage();
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                $article->setImage(null);
            }
        }

        if ($request->request->get('remove_video') === '1') {
            if ($article->getVideo()) {
                $oldVideoPath = $this->getParameter('videos_dir') . '/' . $article->getVideo();
                if (file_exists($oldVideoPath)) {
                    unlink($oldVideoPath);
                }
                $article->setVideo(null);
            }
        }

        $em->flush();

        $this->addFlash('success', 'Article updated successfully!');
        return $this->redirectToRoute('article_feed');
    }

    return $this->render('article/edit.html.twig', [
        'article' => $article
    ]);
}


#[Route('/comment/{id}/like', name: 'app_comment_like', methods: ['POST'])]
public function Commentlike(
    int $id,
    CommentRepository $commentRepo,
    EntityManagerInterface $em,
    NotificationService $notificationService,
    NotificationRepository $notificationRepo

    
): JsonResponse {
    $user = $this->getUser();
    if (!$user) {
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    $comment = $commentRepo->find($id);
    if (!$comment) {
        return new JsonResponse(['error' => 'Comment not found'], 404);
    }

    // Toggle UNLIKE
    if ($comment->isLikedByUser($user)) {
        $comment->removeLikedBy($user);
        $em->flush();

        return new JsonResponse([
            'liked' => false,
            'count' => $comment->getLikesCount(),
        ]);
    }

    // LIKE
    $comment->addLikedBy($user);
    $em->flush();

    // ✅ NOTIFICATION (sans mentionner le user)
    try {
    $commentAuthor = $comment->getAuthor();

    if ($commentAuthor && $commentAuthor->getId() !== $user->getId()) {

        // Vérifie si une notif "comment-like" existe déjà pour CE commentaire
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
                // ✅ ton feed utilise #article-ID (pas /app/articles/{id})
                $comment->getArticle() ? '/app/articles#article-' . $comment->getArticle()->getId() : null,
                'comment-like',
                Notification::PRIORITY_NORMAL
            );
        }
    }
}catch (\Throwable $e) {
        // On ne bloque jamais le like si notif fail
    }

    return new JsonResponse([
        'liked' => true,
        'count' => $comment->getLikesCount(),
    ]);
}


#[Route('/{id}/delete', name: 'article_delete')]
#[IsGranted('ROLE_USER')]
public function delete(Article $article, EntityManagerInterface $em): Response
{
    if ($this->getUser() !== $article->getAuthor()) {
        throw $this->createAccessDeniedException('You are not allowed to delete this article');
    }

    // Delete associated image if exists
    if ($article->getImage()) {
        $imagePath = $this->getParameter('articles_dir') . '/' . $article->getImage();
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    // Delete associated video if exists
    if ($article->getVideo()) {
        $videoPath = $this->getParameter('videos_dir') . '/' . $article->getVideo();
        if (file_exists($videoPath)) {
            unlink($videoPath);
        }
    }

    $em->remove($article);
    $em->flush();

    $this->addFlash('success', 'Article deleted successfully!');
    return $this->redirectToRoute('article_feed');
}



#[Route('/link-preview', name: 'link_preview', methods: ['POST'])]
public function linkPreview(
    Request $request,
    LinkPreviewService $previewService
): JsonResponse {
    $url = $request->toArray()['url'] ?? null;

    if (!$url) {
        return $this->json(['error' => 'URL manquante'], 400);
    }

    try {
        return $this->json($previewService->fetch($url));
    } catch (\Exception $e) {
        return $this->json(['error' => 'Impossible de charger le preview'], 500);
    }
}

}