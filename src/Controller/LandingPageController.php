<?php

namespace App\Controller;


use App\Repository\CourseRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class LandingPageController extends AbstractController{
#[Route('/', name: 'app_landing_page')]
public function index(CourseRepository $courseRepo): Response
{
    // Tous les cours
    $coursesAll = $courseRepo->findAll();

    // IDs spécifiques que tu veux récupérer
    $specificIds = [15, 10, 12, 9];

    // On récupère les cours correspondant à ces IDs
    $coursesUnordered = $courseRepo->findBy(['id' => $specificIds]);

$courses = [];
foreach ($specificIds as $id) {
    foreach ($coursesUnordered as $course) {
        if ($course->getId() === $id) {
            $courses[] = $course;
            break;
        }
    }
}


    // Si au moins un des cours n'existe pas → on affiche tous les cours
    if (count($courses) < count($specificIds)) {
        $courses = $coursesAll;
    }

    return $this->render('landing_page/index.html.twig', [
        'courses' => $courses,
    ]);
}

}
