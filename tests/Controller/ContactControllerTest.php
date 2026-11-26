<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ContactController;
use App\Service\LearnerContactService;
use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;
use Twig\Environment;

final class ContactControllerTest extends TestCase
{
    public function testSuccessfulContactCreatesAConversionOnlyAfterSendingTheMessage(): void
    {
        $request = $this->request('POST', [
            '_token' => 'test', 'firstname' => 'Test', 'lastname' => 'Member',
            'email' => 'member@example.com', 'message' => 'Please demonstrate team training.',
        ]);
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::once())->method('send');

        $response = $this->controller($request)->contact($request, new LearnerContactService($mailer, Validation::createValidator()));

        self::assertSame(303, $response->getStatusCode());
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $request->getSession()->get('analytics_contact_id'));
    }

    public function testInvalidContactCannotCreateAConversion(): void
    {
        $request = $this->request('POST', ['_token' => 'test', 'email' => 'invalid']);
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::never())->method('send');

        $this->controller($request)->contact($request, new LearnerContactService($mailer, Validation::createValidator()));

        self::assertFalse($request->getSession()->has('analytics_contact_id'));
    }

    public function testInvalidCsrfCannotSendAContactRequest(): void
    {
        $request = $this->request('POST');
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::never())->method('send');
        $this->expectException(AccessDeniedException::class);

        $this->controller($request, false)->contact($request, new LearnerContactService($mailer, Validation::createValidator()));
    }

    public function testConfirmationIsConsumedOnceAndCannotBeForgedByTheUrl(): void
    {
        $request = $this->request('GET');
        $request->query->set('success', '1');
        $request->getSession()->set('analytics_contact_id', 'confirmed-test-lead');
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))->method('render')->withConsecutive(
            ['contact/contact.html.twig', ['demo' => false, 'analyticsLeadId' => 'confirmed-test-lead']],
            ['contact/contact.html.twig', ['demo' => false, 'analyticsLeadId' => null]],
        )->willReturn('<html></html>');
        $controller = $this->controller($request, true, $twig);
        $service = new LearnerContactService($this->createMock(MailerService::class), Validation::createValidator());

        $controller->contact($request, $service);
        $controller->contact($request, $service);
    }

    private function request(string $method, array $data = []): Request
    {
        $request = Request::create('/contact', $method, $data);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function controller(Request $request, bool $validCsrf = true, ?Environment $twig = null): ContactController
    {
        $stack = new RequestStack();
        $stack->push($request);
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validCsrf);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/contact');
        $twig ??= $this->createMock(Environment::class);
        $controller = new ContactController();
        $controller->setContainer(new ServiceLocator([
            'request_stack' => static fn () => $stack,
            'security.csrf.token_manager' => static fn () => $csrf,
            'router' => static fn () => $router,
            'twig' => static fn () => $twig,
        ]));

        return $controller;
    }
}
