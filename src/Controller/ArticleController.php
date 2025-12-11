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

        // ---- IMAGE UPLOAD ----
        $imageFile = $request->files->get('image');

        if ($imageFile) {
            $newFilename = uniqid().'_'.$imageFile->getClientOriginalName();
            $imageFile->move(
                $this->getParameter('articles_dir'), 
                $newFilename
            );

            // Met à jour le nom dans l'entité
            $article->setImage($newFilename);
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

        $em->remove($article);
        $em->flush();

        $this->addFlash('success', 'Article deleted successfully!');
        return $this->redirectToRoute('article_feed');
    }
}