<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Scorm\BilingualScormExportService;
use App\Service\Scorm\Exception\ScormGenerationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[IsGranted('ROLE_ADMIN')]
final class BilingualScormExportController extends AbstractController
{
    #[Route('/admin/scorm/export/bilingual', name: 'admin_scorm_bilingual_export', methods: ['POST'])]
    public function __invoke(Request $request, BilingualScormExportService $exportService): BinaryFileResponse|RedirectResponse
    {
        if (!$this->isCsrfTokenValid('scorm_bilingual_export', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'The security token is invalid. Please try again.');

            return $this->redirectToRoute('admin_scorm_generator');
        }

        $title = trim((string) $request->request->get('title'));
        $identifier = trim((string) $request->request->get('identifier'));
        $englishTitle = trim((string) $request->request->get('title_en'));
        $germanTitle = trim((string) $request->request->get('title_de'));
        $passThreshold = $request->request->getInt('pass_threshold', 80);

        try {
            $archivePath = $exportService->export(
                $identifier,
                $title,
                $englishTitle,
                $germanTitle,
                $passThreshold,
                [
                    'video_en' => $request->files->get('video_en'),
                    'video_de' => $request->files->get('video_de'),
                    'quiz_en' => $request->files->get('quiz_en'),
                    'quiz_de' => $request->files->get('quiz_de'),
                ],
            );
        } catch (ScormGenerationException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('admin_scorm_generator');
        }

        $slug = (new AsciiSlugger())->slug($title)->lower()->toString();
        $response = $this->file(
            $archivePath,
            ($slug !== '' ? $slug : 'bilingual-course').'-scorm-en-de.zip',
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
