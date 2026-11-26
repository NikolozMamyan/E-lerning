<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PurchaseTrackingService
{
    public function __construct(#[Autowire(env: 'STRIPE_SECRET_KEY')] private string $stripeKey)
    {
    }

    public function fromCheckout(Session $session): ?array
    {
        if ($session->payment_status !== 'paid' || !$session->id || $session->amount_total === null) {
            return null;
        }

        $subscription = $session->mode === 'subscription';
        $quantity = max(1, (int) ($session->metadata->quantity ?? 1));
        $value = (int) $session->amount_total / 100;

        return [
            'transaction_id' => $session->id,
            'value' => $value,
            'currency' => strtoupper((string) $session->currency),
            'purchase_type' => $subscription ? 'subscription' : 'course',
            'items' => [[
                'item_id' => $subscription ? 'subscription' : 'course_'.($session->metadata->course_id ?? ''),
                'item_name' => $subscription ? 'Learning subscription' : 'Online training',
                'price' => round($value / $quantity, 2),
                'quantity' => $quantity,
            ]],
        ];
    }

    public function subscription(string $sessionId, User $user): ?array
    {
        if ($sessionId === '') {
            return null;
        }

        try {
            Stripe::setApiKey($this->stripeKey);
            $session = Session::retrieve($sessionId);
        } catch (ApiErrorException) {
            return null;
        }

        if ($session->mode !== 'subscription' || (string) ($session->metadata->user_id ?? '') !== (string) $user->getId()) {
            return null;
        }

        return $this->fromCheckout($session);
    }
}
