<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\User;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GuestCourseCheckoutService
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function createCheckout(Course $course, ?User $user = null): Session
    {
        $price = $this->euroPrice($course);
        $this->configureStripe();

        $metadata = [
            'context' => $user ? 'employee_purchase' : 'guest_purchase',
            'course_id' => (string) $course->getId(),
        ];
        if ($user) {
            $metadata['user_id'] = (string) $user->getId();
        }

        $parameters = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_creation' => 'always',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $price,
                    'product_data' => ['name' => $course->getTitle()],
                ],
                'quantity' => 1,
            ]],
            'metadata' => $metadata,
            'success_url' => $this->urlGenerator->generate('app_catalog_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->urlGenerator->generate('app_public_catalog', [], UrlGeneratorInterface::ABSOLUTE_URL).'#course-'.$course->getId(),
        ];

        if ($user) {
            $parameters['customer_email'] = $user->getEmail();
        }

        return Session::create($parameters);
    }

    /** @return array{paid: bool, courseId: ?int, courseTitle: ?string, amount: ?float, currency: string} */
    public function paymentSummary(string $sessionId): array
    {
        $this->configureStripe();
        $session = Session::retrieve($sessionId);
        $metadata = $session->metadata ?? (object) [];
        $context = $metadata->context ?? null;

        if (!in_array($context, ['guest_purchase', 'employee_purchase'], true)) {
            throw new \RuntimeException('This payment session does not belong to the public catalogue.');
        }

        return [
            'paid' => ($session->payment_status ?? null) === 'paid',
            'courseId' => isset($metadata->course_id) ? (int) $metadata->course_id : null,
            'courseTitle' => isset($session->line_items) ? null : null,
            'amount' => isset($session->amount_total) ? ((int) $session->amount_total) / 100 : null,
            'currency' => strtoupper((string) ($session->currency ?? 'EUR')),
        ];
    }

    private function euroPrice(Course $course): int
    {
        foreach ($course->getCoursePrices() as $price) {
            if (strtoupper($price->getCurrency()) === 'EUR' && $price->getPrice() > 0) {
                return $price->getPrice();
            }
        }

        throw new \RuntimeException('This course is not currently available for individual purchase.');
    }

    private function configureStripe(): void
    {
        $secret = $_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('Stripe is not configured.');
        }

        Stripe::setApiKey($secret);
    }
}
