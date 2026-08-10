<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class LocaleSubscriberTest extends TestCase
{
    public function testItalianQueryLocaleIsAppliedAndStored(): void
    {
        $request = Request::create('/app/course/1?_locale=it');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $subscriber = new LocaleSubscriber();
        $subscriber->onKernelRequest(new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));

        self::assertSame('it', $request->getLocale());
        self::assertSame('it', $request->getSession()->get('_locale'));
    }

    public function testUnsupportedQueryLocaleIsIgnored(): void
    {
        $request = Request::create('/app/course/1?_locale=invalid');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $subscriber = new LocaleSubscriber();
        $subscriber->onKernelRequest(new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));

        self::assertSame('en', $request->getLocale());
        self::assertNull($request->getSession()->get('_locale'));
    }
}
