<?php

namespace App\Controller;


use App\Service\LearnerContactService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, LearnerContactService $contactService): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid contact request.');
            }

            try {
                $contactService->send($request->request->all());
                $this->addFlash('success', 'Votre message a bien été envoyé. Merci !');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_contact', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contact/contact.html.twig', [
            'demo' => $request->query->getBoolean('demo'),
        ]);
    }
}
