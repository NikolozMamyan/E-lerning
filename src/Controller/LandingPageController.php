<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CourseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingPageController extends AbstractController
{
    #[Route('/', name: 'app_landing_page', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, ArticleRepository $articleRepository): Response
    {
        $specificIds = [15, 10, 12, 9];
        $coursesById = [];

        foreach ($courseRepository->findBy(['id' => $specificIds]) as $course) {
            $coursesById[$course->getId()] = $course;
        }

        $courses = array_values(array_filter(array_map(
            static fn (int $id) => $coursesById[$id] ?? null,
            $specificIds,
        )));

        if (count($courses) < count($specificIds)) {
            $courses = array_slice($courseRepository->findAll(), 0, 4);
        }

        return $this->render('landing_page/index.html.twig', [
            'courses' => $courses,
            'trendingArticles' => $articleRepository->findTrending(3),
        ]);
    }
}
