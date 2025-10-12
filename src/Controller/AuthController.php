<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthController extends AbstractController
{
   #[Route('/api/register', name: 'api_register', methods: ['POST'])]
public function register(
    Request $request,
    UserRepository $userRepository,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $em,
    NotificationService $notificationService, // 👈 injecter ton service
    LoggerInterface $logger // 👈 pour loguer les erreurs
): JsonResponse {
    $data = json_decode($request->getContent(), true);

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

    $user->setPassword(
        $passwordHasher->hashPassword($user, $data['password'])
    );

    $token = bin2hex(random_bytes(32));
    $expiresAt = (new \DateTime())->modify('+4 hour');
    $user->setApiToken($token);
    $user->setTokenExpiresAt($expiresAt);

    $em->persist($user);
    $em->flush();

    // 👇 Création de la notification de bienvenue
    try {
        $notificationService->createEntityNotification(
            $user,
            '👋 Welcome!',
            $user, // entité liée (ici on peut mettre l'user lui-même)
            "Hello dear, please complete your profile to get started.",
            Notification::TYPE_INFO,
            '/settings', // lien vers la page profil
            'profile-completion',
            Notification::PRIORITY_LOW
        );

        $logger->info('Notification created for new user registration', [
            'userId' => $user->getId(),
        ]);
    } catch (\Exception $e) {
        $logger->error('Failed to create registration notification', [
            'userId' => $user->getId(),
            'error' => $e->getMessage()
        ]);
    }

    $response = new JsonResponse([
        'message' => 'Inscription réussie',
        'user' => [
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]
    ], 201);

    $response->headers->setCookie(
        Cookie::create('AUTH_TOKEN')
            ->withValue($token)
            ->withHttpOnly(true)
            ->withSecure(true) // en prod : true
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
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], 400);
        }

        $user = $userRepository->findOneBy(['email' => $data['email']]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime())->modify('+4 hour');
        $user->setApiToken($token);
        $user->setTokenExpiresAt($expiresAt);
        $em->flush();

        $response = new JsonResponse([
            'message' => 'Connexion réussie',
            'user' => [
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);

        $response->headers->setCookie(
            Cookie::create('AUTH_TOKEN')
                ->withValue($token)
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withPath('/')
                ->withExpires($expiresAt->getTimestamp())
        );

        return $response;
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $token = $request->cookies->get('AUTH_TOKEN');
    
        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant'], 401);
        }
    
        $user = $userRepository->findOneBy(['apiToken' => $token]);
    
        if (!$user) {
            return new JsonResponse(['error' => 'Token invalide'], 401);
        }
    
        $user->setApiToken(null);
        $user->setTokenExpiresAt(null);
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

        return new JsonResponse([
            'id' => $user->getId(),  
            'userName'=>$user->getUserName(),
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
public function googleCallback(ClientRegistry $clientRegistry, EntityManagerInterface $em, UserRepository $userRepository): RedirectResponse
{
    $client = $clientRegistry->getClient('google');
    $googleUser = $client->fetchUser();

    $email = $googleUser->getEmail();
    $name = $googleUser->getName();

    $user = $userRepository->findOneBy(['email' => $email]);

    if (!$user) {
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($name);
        $user->setRoles(['ROLE_EMPLOYEE']); // par défaut
        $em->persist($user);
        $em->flush();
    }

    // même logique token
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new \DateTime())->modify('+4 hour');
    $user->setApiToken($token);
    $user->setTokenExpiresAt($expiresAt);
    $em->flush();

    $response = new RedirectResponse('/app/dashboard'); // ← change la route ici (front ou twig)
    $response->headers->setCookie(
        Cookie::create('AUTH_TOKEN')
            ->withValue($token)
            ->withHttpOnly(true)
            ->withSecure(true) // mettre true en prod
            ->withPath('/')
            ->withExpires($expiresAt->getTimestamp())
    );

    return $response;
}
}
