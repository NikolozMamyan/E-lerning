<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(
        UserRepository $userRepository,
        ArticleRepository $articleRepository,
        CourseRepository $courseRepository,
    ): Response
    {
        $articles = $articleRepository->findBy([], ['createdAt' => 'DESC']);

        $response = $this->render('seo/sitemap.xml.twig', [
            'profiles' => $userRepository->findPublicProfilesForSitemap(),
            'courses' => $courseRepository->findBy([], ['title' => 'ASC']),
            'articles' => $articles,
            'latestArticle' => $articles[0] ?? null,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(UrlGeneratorInterface $urlGenerator): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /company/',
            'Disallow: /settings',
            'Disallow: /notifications',
            'Disallow: /reset-password/',
            'Disallow: /app/dashboard',
            'Disallow: /app/progress',
            'Disallow: /app/transactions',
            'Disallow: /app/pay/',
            'Disallow: /app/certificates',
            'Disallow: /app/invoices/',
            'Disallow: /app/course/*/quiz',
            'Disallow: /catalog/payment/',
            '',
            'Sitemap: '.$urlGenerator->generate('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '',
        ]);

        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
