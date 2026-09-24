<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\PublicCommunityFeedPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PublicCommunityFeedController extends AbstractController
{
    #[Route('/api/public/community-feed', name: 'api_public_community_feed', methods: ['GET'])]
    public function __invoke(
        Request $request,
        ArticleRepository $articles,
        PublicCommunityFeedPresenter $presenter,
    ): JsonResponse {
        $limit = max(1, min(12, $request->query->getInt('limit', 6)));
        $offset = max(0, $request->query->getInt('offset'));
        $feed = $articles->findFeed('latest', null, $limit, $offset);
        $total = $articles->count([]);

        $response = $this->json([
            'items' => array_map(
                static fn (Article $article): array => $presenter->present($article),
                $feed,
            ),
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'hasMore' => $offset + count($feed) < $total,
            ],
        ]);
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(120);

        return $response;
    }
}
