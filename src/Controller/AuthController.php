<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notification;
use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use App\Service\NotificationService;
use App\Service\SessionTokenService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        LoggerInterface $logger,
        SessionTokenService $sessionTokenService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'], $data['password'], $data['userName'], $data['role'])) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        if ($userRepository->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['error' => 'Email already in use'], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setUsername($data['userName']);

        if ($data['role'] === 'employee') {
            $user->setRoles(['ROLE_EMPLOYEE']);
        } elseif ($data['role'] === 'company') {
            $user->setRoles(['ROLE_COMPANY']);
        } else {
            return new JsonResponse(['error' => 'Role invalide'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        $em->persist($user);
        $em->flush();

        // Notification bienvenue
        try {
            $notificationService->createEntityNotification(
                $user,
                '👋 Welcome!',
                $user,
                "Hello dear, please complete your profile to get started.",
                Notification::TYPE_INFO,
                '/settings',
                'profile-completion',
                Notification::PRIORITY_LOW
            );
        } catch (\Exception $e) {
            $logger->error('Failed to create registration notification', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }

        // ✅ créer une session (multi-support)
        $device = $data['device'] ?? 'web';
        $created = $sessionTokenService->createSession($user, $device, 90);
        $plainToken = $created['plainToken'];
        $expiresAt = $created['session']->getExpiresAt();

        $response = new JsonResponse([
            'message' => 'Inscription réussie',
            'token' => $plainToken, // utile pour l’app
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ], 201);

        // Cookie pour le site
        $response->headers->setCookie(
            Cookie::create('AUTH_TOKEN')
                ->withValue($plainToken)
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withSameSite('none')   // IMPORTANT pour WebView / cross-site
                ->withPath('/')
                ->withExpires($expiresAt->getTimestamp())
        );

        return $response;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SessionTokenService $sessionTokenService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], 400);
        }

        $user = $userRepository->findOneBy(['email' => $data['email']]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // ✅ nouvelle session au lieu d’écraser
        $device = $data['device'] ?? 'web';
        $created = $sessionTokenService->createSession($user, $device, 90);
        $plainToken = $created['plainToken'];
        $expiresAt = $created['session']->getExpiresAt();

        $response = new JsonResponse([
            'message' => 'Connexion réussie',
            'token' => $plainToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);

        $response->headers->setCookie(
            Cookie::create('AUTH_TOKEN')
                ->withValue($plainToken)
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withSameSite('none')
                ->withPath('/')
                ->withExpires($expiresAt->getTimestamp())
        );

        return $response;
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(
        Request $request,
        EntityManagerInterface $em,
        UserSessionRepository $sessionRepo
    ): JsonResponse {
        // cookie (site) ou bearer (app)
        $token = $request->cookies->get('AUTH_TOKEN');

        $authHeader = $request->headers->get('Authorization');
        if (!$token && $authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }

        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant'], 401);
        }

        $tokenHash = hash('sha256', $token);
        $session = $sessionRepo->findOneBy(['tokenHash' => $tokenHash]);

        if (!$session) {
            return new JsonResponse(['error' => 'Token invalide'], 401);
        }

        $session->setRevokedAt(new \DateTimeImmutable());
        $em->flush();

        $response = new JsonResponse(['message' => 'Déconnexion réussie']);
        $response->headers->clearCookie('AUTH_TOKEN');
        return $response;
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // Option A: depuis la relation (si elle est bien mappée)
    $sessions = [];
    foreach ($user->getUserSessions() as $s) {
        $sessions[] = [
            'id' => $s->getId(),
            'device' => $s->getDevice(),
            'createdAt' => $s->getCreatedAt()->format(DATE_ATOM),
            'expiresAt' => $s->getExpiresAt()->format(DATE_ATOM),
            'revokedAt' => $s->getRevokedAt()?->format(DATE_ATOM),
            'lastUsedAt' => $s->getLastUsedAt()?->format(DATE_ATOM),
            'active' => $s->isActive(),
        ];
    }


        return new JsonResponse([
            'id' => $user->getId(),
            'userName' => $user->getUserName(),
             'sessions' => $sessions,
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    // -------------------
    // GOOGLE OAuth
    // -------------------
    #[Route('/google', name: 'api_google_start')]
    public function googleConnect(ClientRegistry $clientRegistry)
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/google/callback', name: 'api_google_callback')]
    public function googleCallback(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        SessionTokenService $sessionTokenService
    ): RedirectResponse {
        $client = $clientRegistry->getClient('google');
        $googleUser = $client->fetchUser();

        $email = $googleUser->getEmail();
        $name = $googleUser->getName();

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($name);
            $user->setRoles(['ROLE_EMPLOYEE']);
            $em->persist($user);
            $em->flush();
        }

        // ✅ nouvelle session
        $created = $sessionTokenService->createSession($user, 'web', 90);
        $plainToken = $created['plainToken'];
        $expiresAt = $created['session']->getExpiresAt();

        $response = new RedirectResponse('/app/dashboard');
        $response->headers->setCookie(
            Cookie::create('AUTH_TOKEN')
                ->withValue($plainToken)
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withSameSite('none')
                ->withPath('/')
                ->withExpires($expiresAt->getTimestamp())
        );

        return $response;
    }

    #[Route('/api/logout-all', name: 'api_logout_all', methods: ['POST'])]
public function logoutAll(EntityManagerInterface $em): JsonResponse
{
    $user = $this->getUser();
    if (!$user) return new JsonResponse(['error' => 'Non authentifié'], 401);

    // revoke toutes les sessions
    $now = new \DateTimeImmutable();
    foreach ($user->getUserSessions() ?? [] as $s) { // si tu ajoutes OneToMany
        $s->setRevokedAt($now);
    }
    $em->flush();

    $response = new JsonResponse(['message' => 'Toutes les sessions ont été révoquées']);
    $response->headers->clearCookie('AUTH_TOKEN');
    return $response;
}

}
