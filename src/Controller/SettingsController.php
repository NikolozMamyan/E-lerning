<?php

namespace App\Controller;

use App\Form\UserType;
use App\Form\AvatarType;
use App\Form\AddressType;
use App\Form\EducationType;
use App\Form\PersonalInfoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('show_login');
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Gestion upload avatar
            $avatarFile = $form->get('avatar')->getData();
            if ($avatarFile) {
                $newFilename = uniqid().'.'.$avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l’upload de l’image.');
                }

                $user->setAvatar($newFilename);
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès ✅');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }


#[Route('/settings/edit/{section}', name: 'app_settings_edit', methods: ['GET','POST'])]
public function editSection(string $section, Request $request, EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    if (!$user) {
        throw $this->createAccessDeniedException();
    }

    $formClass = match ($section) {
        'personal'  => PersonalInfoType::class,
        'address'   => AddressType::class,
        'education' => EducationType::class,
        'avatar'    => AvatarType::class,
        default     => PersonalInfoType::class,
    };

    $form = $this->createForm($formClass, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        if ($section === 'avatar') {
            $avatarFile = $form->get('avatar')->getData();
            if ($avatarFile) {
                $newFilename = uniqid().'.'.$avatarFile->guessExtension();
                try {
                    $avatarFile->move($this->getParameter('uploads_directory'), $newFilename);
                } catch (FileException $e) {
                    return $this->json(['success' => false, 'error' => 'Upload failed']);
                }
                $user->setAvatar($newFilename);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'html' => $this->renderView("settings/_card_$section.html.twig", [
                'user' => $user
            ])
        ]);
    }

    return $this->render('settings/_form.html.twig', [
        'form' => $form->createView(),
        'section' => $section
    ]);
}


}
