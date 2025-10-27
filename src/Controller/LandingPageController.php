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
    // Tous les cours
    $coursesAll = $courseRepo->findAll();

    // IDs spécifiques que tu veux récupérer
    $specificIds = [9, 10, 12, 15];

    // On récupère les cours correspondant à ces IDs
    $courses = $courseRepo->findBy(['id' => $specificIds]);

    // Si au moins un des cours n'existe pas → on affiche tous les cours
    if (count($courses) < count($specificIds)) {
        $courses = $coursesAll;
    }

    return $this->render('landing_page/index.html.twig', [
        'courses' => $courses,
    ]);
}

}
