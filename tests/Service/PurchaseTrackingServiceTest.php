<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\PurchaseTrackingService;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\Checkout\Session;
use Stripe\HttpClient\ClientInterface;

final class PurchaseTrackingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        \Stripe\Stripe::setApiKey(null);
    }

    /** @dataProvider unconfirmedPayments */
    public function testUnconfirmedPaymentDoesNotProduceAConversion(string $status, ?int $amount): void
    {
        $session = Session::constructFrom([
            'id' => 'cs_test_pending', 'payment_status' => $status, 'amount_total' => $amount,
        ]);

        self::assertNull((new PurchaseTrackingService('sk_test_unused'))->fromCheckout($session));
    }

    public function unconfirmedPayments(): array
    {
        return [['unpaid', 15000], ['no_payment_required', 0], ['paid', null]];
    }

    public function testPaidTeamPurchaseContainsRevenueAndQuantityWithoutPersonalDetails(): void
    {
        $session = Session::constructFrom([
            'id' => 'cs_test_paid', 'mode' => 'payment', 'payment_status' => 'paid',
            'amount_total' => 15000, 'currency' => 'eur',
            'customer_email' => 'buyer@example.com',
            'metadata' => ['course_id' => '7', 'quantity' => '3', 'target_email' => 'learner@example.com'],
        ]);
        $purchase = (new PurchaseTrackingService('sk_test_unused'))->fromCheckout($session);

        self::assertSame('cs_test_paid', $purchase['transaction_id']);
        self::assertEquals(150, $purchase['value']);
        self::assertSame('EUR', $purchase['currency']);
        self::assertSame('course_7', $purchase['items'][0]['item_id']);
        self::assertSame(3, $purchase['items'][0]['quantity']);
        self::assertEquals(50, $purchase['items'][0]['price']);
        self::assertStringNotContainsString('example.com', json_encode($purchase));
    }

    /** @dataProvider subscriptionSessions */
    public function testSubscriptionConversionRequiresPaymentAndTheCurrentBuyer(string $owner, string $status, string $mode, bool $expected): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())->method('request')->willReturn([
            json_encode([
                'id' => 'cs_test_subscription', 'object' => 'checkout.session',
                'mode' => $mode, 'payment_status' => $status,
                'amount_total' => 3990, 'currency' => 'eur', 'metadata' => ['user_id' => $owner],
            ]), 200, [],
        ]);
        ApiRequestor::setHttpClient($http);

        $purchase = (new PurchaseTrackingService('sk_test_unused'))->subscription('cs_test_subscription', $user);

        self::assertSame($expected, $purchase !== null);
        if ($expected) {
            self::assertSame('subscription', $purchase['items'][0]['item_id']);
            self::assertSame(39.9, $purchase['value']);
        }
    }

    public function subscriptionSessions(): array
    {
        return [
            ['42', 'paid', 'subscription', true],
            ['99', 'paid', 'subscription', false],
            ['42', 'unpaid', 'subscription', false],
            ['42', 'paid', 'payment', false],
        ];
    }
}
