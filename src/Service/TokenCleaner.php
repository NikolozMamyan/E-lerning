<?php
namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenCleaner
{
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    public function clearTokenFromRequest(Request $request, Response $response): void
    {
        $token = $request->cookies->get('AUTH_TOKEN');

        if (!$token) {
            return;
        }

        $user = $this->userRepository->findOneBy(['apiToken' => $token]);

        if ($user) {
            $user->setApiToken(null);
            $user->setTokenExpiresAt(null);
            $this->em->flush();
        }

        // Supprimer le cookie AUTH_TOKEN dans la réponse
        $response->headers->clearCookie('AUTH_TOKEN');
    }
}
