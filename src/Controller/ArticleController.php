<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/app/articles')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'article_feed', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/feed.html.twig', [
            'articles' => $articleRepository->findBy([], ['createdAt' => 'DESC']),
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



#[Route('/{id}/comment', name: 'article_comment', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function addComment(
    Article $article,
    Request $request,
    EntityManagerInterface $em,
    NotificationService $notificationService
): Response {
    $content = trim($request->request->get('comment', ''));

    if (!$content) {
        return $this->json(['error' => 'Comment cannot be empty'], 400);
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
}