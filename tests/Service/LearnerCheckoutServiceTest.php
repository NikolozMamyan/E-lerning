<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\LearnerCheckoutService;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LearnerCheckoutServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        \Stripe\Stripe::setApiKey(null);
    }

    /** @dataProvider subscriptionPrices */
    public function testSubscriptionUsesTheConfiguredPriceWithoutChangingTheMemberOrReturnUrls(?string $configured, string $expected): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);
        $user->method('getEmail')->willReturn('member@example.com');
        $user->method('hasActiveSubscription')->willReturn(false);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => 'https://learning.example/'.$route);
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())->method('request')->willReturnCallback(
            static function ($method, $url, $headers, $params) use ($expected): array {
                self::assertSame('post', $method);
                self::assertSame('https://api.stripe.com/v1/checkout/sessions', $url);
                self::assertSame([['price' => $expected, 'quantity' => 1]], $params['line_items']);
                self::assertSame('member@example.com', $params['customer_email']);
                self::assertSame(['user_id' => '42'], $params['metadata']);
                self::assertSame('https://learning.example/app_subscription_success?session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
                self::assertSame('https://learning.example/app_payment_cancel', $params['cancel_url']);

                return [json_encode(['id' => 'cs_test_checkout', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/test-checkout']), 200, []];
            }
        );
        ApiRequestor::setHttpClient($http);

        self::assertSame('https://checkout.stripe.com/test-checkout', (new LearnerCheckoutService('sk_test_unused', $urls, $configured))->subscription($user));
    }

    public function subscriptionPrices(): array
    {
        return [
            'existing production price' => [null, 'price_1TGZufGYlvTXCIGy5ILG6jIj'],
            'preproduction price' => ['price_preproduction', 'price_preproduction'],
            'empty optional setting' => ['', 'price_1TGZufGYlvTXCIGy5ILG6jIj'],
        ];
    }
}
