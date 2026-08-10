<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'it'];

    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $requestedLocale = $request->query->get('_locale');

        if (is_string($requestedLocale) && in_array($requestedLocale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($requestedLocale);

            if ($request->hasSession()) {
                $request->getSession()->set('_locale', $requestedLocale);
            }

            return;
        }

        if (!$request->hasPreviousSession()) {
            return;
        }

        $sessionLocale = $request->getSession()->get('_locale', $this->defaultLocale);
        $request->setLocale($this->isSupported($sessionLocale) ? $sessionLocale : $this->defaultLocale);
    }

    private function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
