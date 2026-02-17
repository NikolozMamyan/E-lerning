<?php

namespace App\Security;

use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(
        private UserSessionRepository $sessionRepo,
        private EntityManagerInterface $em
    ) {}

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();

        if (
            in_array($path, [
                '/welcome',
                '/api/register',
                '/api/login',
                '/api/logout',
                '/api/stripe/webhook',
                '/reset-password',
                '/reset-password/check-email',
                '/google',
                '/google/callback'
            ])
            || str_starts_with($path, '/reset-password/reset')
        ) {
            return false;
        }

        return str_starts_with($path, '/api/')
            || (str_starts_with($path, '/app/') && $request->cookies->has('AUTH_TOKEN'))
            || (str_starts_with($path, '/admin/') && $request->cookies->has('AUTH_TOKEN'))
            || (str_starts_with($path, '/company/') && $request->cookies->has('AUTH_TOKEN'))
            || (str_starts_with($path, '/settings/') && $request->cookies->has('AUTH_TOKEN'))
            || (str_starts_with($path, '/notifications/') && $request->cookies->has('AUTH_TOKEN'))
            || (str_starts_with($path, '/contact/') && $request->cookies->has('AUTH_TOKEN'));
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

        $tokenHash = hash('sha256', $token);

        return new SelfValidatingPassport(
            new UserBadge($tokenHash, function (string $tokenHash) {
                $session = $this->sessionRepo->findActiveByTokenHash($tokenHash);

                if (!$session) {
                    throw new CustomUserMessageAuthenticationException('Invalid or expired token');
                }

                // Optionnel: track usage (attention au flush trop fréquent, mais ok)
                $session->setLastUsedAt(new \DateTimeImmutable());
                $this->em->flush();

                return $session->getUser();
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return new JsonResponse(['error' => $exception->getMessage()], 401);
        }

        return new RedirectResponse('/login');
    }
}
