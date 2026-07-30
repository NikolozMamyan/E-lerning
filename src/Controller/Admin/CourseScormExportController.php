<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Enum\ScormLanguage;
use App\Service\Scorm\CourseScormExportService;
use App\Service\Scorm\Exception\ScormGenerationException;
use App\Service\Scorm\ScormCourseInspector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[IsGranted('ROLE_ADMIN')]
final class CourseScormExportController extends AbstractController
{
    #[Route(
        '/admin/scorm/courses/{id}/export/{language}',
        name: 'admin_scorm_export',
        requirements: ['id' => '\\d+', 'language' => 'en|fr|de'],
        methods: ['GET'],
    )]
    public function __invoke(
        Course $course,
        string $language,
        CourseScormExportService $exportService,
        ScormCourseInspector $courseInspector,
    ): BinaryFileResponse|RedirectResponse {
        $scormLanguage = ScormLanguage::tryFrom($language);
        if (!$scormLanguage instanceof ScormLanguage) {
            throw $this->createNotFoundException('Unsupported SCORM language.');
        }

        try {
            $video = $courseInspector->singleVideo($course);
            $archivePath = $exportService->export($course, $scormLanguage);
        } catch (ScormGenerationException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('admin_scorm_generator', ['course' => $course->getId()]);
        }

        $response = $this->file(
            $archivePath,
            $this->downloadFilename($scormLanguage->title($video), $scormLanguage),
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function downloadFilename(?string $title, ScormLanguage $language): string
    {
        $slug = (new AsciiSlugger($language->value))->slug((string) $title)->lower()->toString();

        return ($slug !== '' ? $slug : 'course').'-scorm-'.$language->value.'.zip';
    }
}
