<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Repository\UserSessionRepository;
use App\Security\ApiTokenAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ApiTokenAuthenticatorPublicFeedTest extends TestCase
{
    public function testCommunityFeedEndpointDoesNotRequireAnApiToken(): void
    {
        $authenticator = new ApiTokenAuthenticator(
            $this->createMock(UserSessionRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        self::assertFalse($authenticator->supports(Request::create('/api/public/community-feed')));
        self::assertTrue($authenticator->supports(Request::create('/api/articles')));
    }
}
