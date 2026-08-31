<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AddressType;
use App\Form\AvatarType;
use App\Form\EducationType;
use App\Form\PersonalInfoType;
use App\Form\ProfessionalExperienceType;
use App\Form\UserType;
use App\Service\AvatarStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        AvatarStorage $avatarStorage,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('show_login');
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();
            if ($avatarFile instanceof UploadedFile) {
                try {
                    $avatarStorage->save($user, $avatarFile);
                } catch (\RuntimeException $exception) {
                    $this->addFlash('error', $exception->getMessage());

                    return $this->redirectToRoute('app_settings');
                }
            } else {
                $entityManager->flush();
            }

            $this->addFlash('success', 'Your profile has been updated.');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/settings/edit/{section}', name: 'app_settings_edit', methods: ['GET', 'POST'])]
    public function editSection(
        string $section,
        Request $request,
        EntityManagerInterface $entityManager,
        AvatarStorage $avatarStorage,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $formClass = match ($section) {
            'personal' => PersonalInfoType::class,
            'address' => AddressType::class,
            'education' => EducationType::class,
            'avatar' => AvatarType::class,
            'experience' => ProfessionalExperienceType::class,
            default => throw $this->createNotFoundException('Unknown profile section.'),
        };

        $form = $this->createForm($formClass, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($section === 'avatar') {
                $avatarFile = $form->get('avatar')->getData();
                if ($avatarFile instanceof UploadedFile) {
                    try {
                        $avatarStorage->save($user, $avatarFile);
                    } catch (\RuntimeException $exception) {
                        return $this->json(['success' => false, 'error' => $exception->getMessage()], 422);
                    }
                }
            } else {
                $entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'html' => $this->renderView(sprintf('settings/_card_%s.html.twig', $section), [
                    'user' => $user,
                ]),
            ]);
        }

        return $this->render('settings/_form.html.twig', [
            'form' => $form->createView(),
            'section' => $section,
        ]);
    }
}
