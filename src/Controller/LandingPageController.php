<?php

namespace App\Controller;


use App\Repository\CourseRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class LandingPageController extends AbstractController{
    #[Route('/welcome', name: 'app_landing_page')]
    public function index(CourseRepository $courseRepo): Response
    {
        $courses = $courseRepo->findAll();

        return $this->render('landing_page/index.html.twig', [
            'courses' => $courses,
        ]);
    }
}
