<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Service\PasswordSetupInvitationSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/emails/group', name: 'admin_email_')]
#[IsGranted('ROLE_ADMIN')]
final class EmailController extends AbstractController
{
    #[Route('', name: 'choice', methods: ['GET'])]
    public function choice(): Response
    {
        return $this->render('admin/email_choice.html.twig');
    }

    #[Route('/password-setup', name: 'password_setup', methods: ['GET', 'POST'])]
    public function passwordSetup(
        Request $request,
        UserRepository $userRepository,
        PasswordSetupInvitationSender $invitationSender,
    ): Response {
        $users = $userRepository->findBy([], ['email' => 'ASC']);
        $subject = trim((string) $request->request->get('subject', 'Set up your password'));
        $message = trim((string) $request->request->get('message', ''));
        $selectedUserIds = array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('user_ids')),
            static fn (int $id): bool => $id > 0
        )));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'admin_password_setup_email',
                (string) $request->request->get('_token')
            )) {
                $this->addFlash('danger', 'The form has expired. Please try again.');
            } elseif ($selectedUserIds === []) {
                $this->addFlash('danger', 'Select at least one user.');
            } elseif ($subject === '') {
                $this->addFlash('danger', 'The subject is required.');
            } elseif (mb_strlen($subject) > 255) {
                $this->addFlash('danger', 'The subject must not exceed 255 characters.');
            } elseif ($message === '') {
                $this->addFlash('danger', 'The message is required.');
            } elseif (mb_strlen($message) > 10000) {
                $this->addFlash('danger', 'The message must not exceed 10,000 characters.');
            } else {
                $selectedUsers = $userRepository->createQueryBuilder('u')
                    ->where('u.id IN (:ids)')
                    ->setParameter('ids', $selectedUserIds)
                    ->orderBy('u.email', 'ASC')
                    ->getQuery()
                    ->getResult();

                $result = $invitationSender->sendToUsers($selectedUsers, $subject, $message);

                if ($result['sent'] > 0) {
                    $this->addFlash('success', sprintf(
                        '%d password setup invitation(s) sent.',
                        $result['sent']
                    ));
                }

                if ($result['skipped'] > 0) {
                    $this->addFlash('warning', sprintf(
                        '%d invitation(s) skipped because a recent password link already exists.',
                        $result['skipped']
                    ));
                }

                if ($result['failed'] > 0) {
                    $this->addFlash('danger', sprintf(
                        '%d invitation(s) could not be sent. Check the application logs before retrying.',
                        $result['failed']
                    ));
                }

                if ($result['sent'] > 0 && $result['failed'] === 0) {
                    return $this->redirectToRoute('admin_email_password_setup');
                }
            }
        }

        return $this->render('admin/password_setup_email.html.twig', [
            'users' => $users,
            'preselectedUserIds' => $selectedUserIds,
            'subject' => $subject,
            'message' => $message,
        ]);
    }
}
