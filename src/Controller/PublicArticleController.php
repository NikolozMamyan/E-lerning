<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicArticleController extends AbstractController
{
    #[Route('/articles/{id}-{slug}', name: 'app_public_article', requirements: ['id' => '\\d+', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(int $id, string $slug, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException('Post not found.');
        }

        if ($slug !== $article->getSlug()) {
            return $this->redirectToRoute('app_public_article', [
                'id' => $article->getId(),
                'slug' => $article->getSlug(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }
}
