<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\ORM\EntityManagerInterface;

class SessionTokenService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @return array{plainToken: string, session: UserSession}
     */
    public function createSession(User $user, string $device = 'web', int $hours = 4): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify("+{$hours} hour");

        $session = new UserSession();
        $session->setUser($user);
        $session->setTokenHash($tokenHash);
        $session->setCreatedAt($now);
        $session->setExpiresAt($expiresAt);
        $session->setDevice($device);

        $this->em->persist($session);
        $this->em->flush();

        return ['plainToken' => $plainToken, 'session' => $session];
    }

    public function tokenHash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
