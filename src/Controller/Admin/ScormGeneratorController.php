<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CourseRepository;
use App\Service\Scorm\Exception\ScormGenerationException;
use App\Service\Scorm\ScormCourseInspector;
use App\Service\Scorm\ScormPackageGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ScormGeneratorController extends AbstractController
{
    #[Route('/admin/scorm', name: 'admin_scorm_generator', methods: ['GET'])]
    public function __invoke(
        Request $request,
        CourseRepository $courseRepository,
        ScormCourseInspector $courseInspector,
        ScormPackageGenerator $packageGenerator,
    ): Response {
        $courses = $courseRepository->findBy([], ['title' => 'ASC']);
        $courseId = $request->query->getInt('course');
        $selectedCourse = $courseId > 0 ? $courseRepository->find($courseId) : null;
        $video = null;
        $languageStatuses = [];
        $courseError = null;

        if ($selectedCourse !== null) {
            try {
                $video = $courseInspector->singleVideo($selectedCourse);
                $languageStatuses = $courseInspector->languageStatuses($selectedCourse);
            } catch (ScormGenerationException $exception) {
                $courseError = $exception->getMessage();
            }
        }

        return $this->render('admin/scorm/index.html.twig', [
            'courses' => $courses,
            'selectedCourse' => $selectedCourse,
            'video' => $video,
            'languageStatuses' => $languageStatuses,
            'courseError' => $courseError,
            'maxPackageSize' => $packageGenerator->maxPackageSize(),
        ]);
    }
}
