<?php

namespace App\Controller;


use App\Service\MailerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
        public function contact(Request $request, MailerService $mailService): Response
    {
        if ($request->isMethod('POST')) {
            $firstname = $request->request->get('firstname');
            $lastname = $request->request->get('lastname');
            $email = $request->request->get('email');
            $phone = $request->request->get('phone');
            $message = $request->request->get('message');

            // contenu envoyé à ton adresse
            $mailService->send(
                'contact@les-consultants.com', // destinataire (ton email de réception)
                '📩 Nouveau message de contact',
                'emails/contact.html.twig',
                [
                    'firstname' => $firstname,
                    'lastname'  => $lastname,
                    'email'     => $email,
                    'phone'     => $phone,
                    'message'   => $message,
                ]
            );

            $this->addFlash('success', 'Votre message a bien été envoyé. Merci !');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/contact.html.twig');
    }
}
