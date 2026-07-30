<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Video;
use App\Enum\ScormLanguage;
use App\Service\Scorm\Exception\ScormGenerationException;
use App\Service\Scorm\ScormVideoStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ScormVideoUploadController extends AbstractController
{
    #[Route('/admin/scorm/videos/{id}/upload', name: 'admin_scorm_video_upload', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function __invoke(Request $request, Video $video, ScormVideoStorage $videoStorage): RedirectResponse
    {
        $courseId = $video->getCourse()?->getId();
        if ($courseId === null) {
            throw $this->createNotFoundException('The video is not associated with a course.');
        }

        if (!$this->isCsrfTokenValid('scorm_upload_'.$video->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'The security token is invalid. Please try again.');

            return $this->redirectToGenerator($courseId);
        }

        $language = ScormLanguage::tryFrom((string) $request->request->get('language'));
        if (!$language instanceof ScormLanguage) {
            $this->addFlash('danger', 'Select a supported language.');

            return $this->redirectToGenerator($courseId);
        }

        $uploadedFile = $request->files->get('video');

        try {
            $videoStorage->save(
                $video,
                $language,
                (string) $request->request->get('title'),
                $uploadedFile instanceof UploadedFile ? $uploadedFile : null,
            );
            $this->addFlash('success', sprintf('%s SCORM video saved successfully.', $language->label()));
        } catch (ScormGenerationException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToGenerator($courseId, $language);
    }

    private function redirectToGenerator(int $courseId, ?ScormLanguage $language = null): RedirectResponse
    {
        $parameters = ['course' => $courseId];
        if ($language instanceof ScormLanguage) {
            $parameters['_fragment'] = 'language-'.$language->value;
        }

        return $this->redirectToRoute('admin_scorm_generator', $parameters);
    }
}
