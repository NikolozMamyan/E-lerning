<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();
    
        // Ne pas activer l'authenticator pour ces routes API publiques
        if (in_array($path, ['/api/register', '/api/login', '/api/logout'])) {
            return false;
        }
    
        // Activer si :
        // - requête API (/api/)
        // - ou requête sur une page HTML (/app/) et le cookie est présent
        return str_starts_with($path, '/api/') ||
               str_starts_with($path, '/app/') && $request->cookies->has('AUTH_TOKEN');
    }
    

    public function authenticate(Request $request): Passport
    {
        $token = null;

        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }

        if (!$token && $request->cookies->has('AUTH_TOKEN')) {
            $token = $request->cookies->get('AUTH_TOKEN');
        }

        if (!$token) {
            throw new CustomUserMessageAuthenticationException('No token provided');
        }

        return new SelfValidatingPassport(new UserBadge($token, function (string $token) {
            $user = $this->userRepository->findOneBy(['apiToken' => $token]);

            if (!$user) {
                throw new CustomUserMessageAuthenticationException('Invalid API Token');
            }

            if ($user->getTokenExpiresAt() < new \DateTime()) {
                throw new CustomUserMessageAuthenticationException('Token expired');
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Si c'est une requête API, on garde une réponse JSON
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return new JsonResponse(['error' => $exception->getMessage()], 401);
        }
    
        // Pour une page HTML, on redirige vers une page sympa
        return new RedirectResponse('/login');
    }
    
}
