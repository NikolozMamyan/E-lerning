<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LearnerCheckoutService
{
    public function __construct(#[Autowire(env: 'STRIPE_SECRET_KEY')] private string $stripeKey, private UrlGeneratorInterface $urls) {}

    public function course(User $user, Course $course): string
    {
        $amount = null;
        foreach ($course->getCoursePrices() as $price) if ($price->getCurrency() === 'EUR') { $amount = $price->getPrice(); break; }
        if ($amount === null || $amount <= 0) throw new \InvalidArgumentException('This course is not available for purchase.');
        \Stripe\Stripe::setApiKey($this->stripeKey);
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment', 'payment_method_types' => ['card'],
            'line_items' => [['price_data' => ['currency' => 'eur', 'unit_amount' => $amount, 'product_data' => ['name' => $course->getTitle()]], 'quantity' => 1]],
            'customer_email' => $user->getEmail(), 'metadata' => ['user_id' => (string) $user->getId(), 'course_id' => (string) $course->getId()],
            'success_url' => $this->urls->generate('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->urls->generate('app_payment_cancel', ['c' => $course->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
        return (string) $session->url;
    }

    public function subscription(User $user): string
    {
        if ($user->hasActiveSubscription()) throw new \InvalidArgumentException('Your subscription is already active.');
        \Stripe\Stripe::setApiKey($this->stripeKey);
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'subscription', 'payment_method_types' => ['card'], 'line_items' => [['price' => 'price_1TGZufGYlvTXCIGy5ILG6jIj', 'quantity' => 1]],
            'customer_email' => $user->getEmail(), 'metadata' => ['user_id' => (string) $user->getId()],
            'success_url' => $this->urls->generate('app_subscription_success', [], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->urls->generate('app_payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
        return (string) $session->url;
    }
}
