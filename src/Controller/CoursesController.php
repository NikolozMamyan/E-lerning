<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\VideoRepository;
use App\Repository\CourseRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CoursesController extends AbstractController
{
#[Route('app/courses', name: 'app_courses')]
public function index(
    CourseRepository $courseRepo,
    ProgressRepository $progressRepo,
    EnrollmentRepository $enrollmentRepo,
    CategoryRepository $categoryRepo
): Response {
    $user = $this->getUser();
    $courses = $courseRepo->findAll();

    // ✅ Récupérer les catégories pour le filtre (ordre alphabétique)
    $categories = $categoryRepo->findBy([], ['name' => 'ASC']);

    // Préparer tableau des accès
    $subscription = $user ? $user->hasActiveSubscription() : false;

    $access = [];
    if ($user) {
        foreach ($courses as $course) {
            $enrollment = $enrollmentRepo->findOneBy([
                'user' => $user,
                'course' => $course,
            ]);
            $access[$course->getId()] = $subscription || (bool) $enrollment;
        }
    }

    // Préparer tableau des progressions
    $progress = [];
    if ($user) {
        $userProgress = $progressRepo->findBy(['user' => $user]);
        foreach ($userProgress as $p) {
            $progress[$p->getVideo()->getId()] = $p;
        }
    }

    return $this->render('courses/index.html.twig', [
        'courses' => $courses,
        'categories' => $categories, // ✅ AJOUT
        'progress' => $progress,
        'access' => $access
    ]);
}



#[Route('app/course/{id}/{videoId?}', name: 'app_course_show', requirements: ['videoId' => '\d+'])]
public function show(
    int $id,
    ?int $videoId,
    CourseRepository $courseRepo,
    VideoRepository $videoRepo,
    ProgressRepository $progressRepo,
    Request $request,
    EnrollmentRepository $enrollmentRepo,
    EntityManagerInterface $em
): Response {
    $course = $courseRepo->find($id);
    if (!$course) {
        throw $this->createNotFoundException('Course not found');
    }

    $user = $this->getUser();

    // 🔒 Vérifier si l’utilisateur a accès au cours
$hasAccess = false;

if ($user) {
    $hasSubscription = $user->hasActiveSubscription();
    
    $enrollment = $enrollmentRepo->findOneBy([
        'user' => $user,
        'course' => $course,
    ]);

    $hasEnrollment = (bool) $enrollment;

    // ✅ Vérifier si l’utilisateur a une subscription OU un enrollment
    $hasAccess = $hasSubscription || $hasEnrollment;
}


if (!$hasAccess) {
    // chercher le prix en euros
    $euroPrice = null;
    foreach ($course->getCoursePrices() as $price) {
        if ($price->getCurrency() === 'EUR') {
            // on divise par 100 car Stripe stocke en centimes
            $euroPrice = number_format($price->getPrice() / 100, 2, ',', ' ');
            break; // on prend le premier prix EUR trouvé
        }
    }

    return $this->render('courses/locked.html.twig', [
        'course' => $course,
        'euroPrice' => $euroPrice,
    ]);
}


    // ✅ Si l’utilisateur a accès, on affiche les vidéos
    $videos = $course->getVideos();

    // vidéo courante
    $currentVideo = null;
    if ($videoId) {
        $currentVideo = $videoRepo->find($videoId);
    }
    if (!$currentVideo && count($videos) > 0) {
        $currentVideo = $videos[0];
    }
    // 👉 Choisir la bonne URL selon la locale
if ($currentVideo) {
    $locale = $request->getLocale();
    if ($locale === 'fr' && $currentVideo->getUrlFr()) {
        $currentVideo->setUrl($currentVideo->getUrlFr());
    }
}
$hasFrench = false;
foreach ($videos as $video) {
    if (!empty($video->getUrlFr())) {
        $hasFrench = true;
        break; // Sortir dès qu'on trouve une vidéo FR
    }
}

// progression
$progress = [];
$updated = false; // pour savoir si on doit faire un flush

if ($user) {
    foreach ($videos as $v) {
        $p = $progressRepo->findOneBy(['user' => $user, 'video' => $v]);

        if ($p) {
            $duration = $v->getDuration() ?? 0;

            // 🔹 Si la vidéo est marquée comme complétée mais que watchedSeconds < duration,
            // on corrige en BDD pour mettre à 100%
            if ($p->isCompleted() && $duration > 0 && $p->getWatchedSeconds() < $duration) {
                $p->setWatchedSeconds($duration);
                $updated = true; // au moins un enregistrement modifié
            }

            $progress[$v->getId()] = [
                'completed' => $p->isCompleted(),
                'watched'   => $p->getWatchedSeconds(),
            ];
        }
    }

    if ($updated) {
        $em->flush();
    }
}


    $completedCount = count(array_filter($progress, fn($p) => $p['completed'] ?? false));
    $progressPercent = count($videos) > 0 ? round(($completedCount / count($videos)) * 100) : 0;

    return $this->render('courses/show.html.twig', [
        'course' => $course,
        'videos' => $videos,
        'currentVideo' => $currentVideo,
        'progress' => $progress,
        'progressPercent' => $progressPercent,
        'hasFrench' =>$hasFrench
    ]);
}


}
