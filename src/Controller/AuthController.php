<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
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
                ->withSecure(false) // en prod : true (HTTPS only)
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
        $expiresAt = (new \DateTime())->modify('+1 hour');
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
                ->withSecure(false)
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
}
