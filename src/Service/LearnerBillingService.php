<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LearnerBillingService
{
    public function __construct(private EnrollmentRepository $enrollments, private SubscriptionRepository $subscriptions, private UrlGeneratorInterface $urls) {}

    public function forUser(User $user): array
    {
        $subscription = $this->subscriptions->findOneBy(['user' => $user, 'isActive' => true], ['startDate' => 'DESC']);
        if ($subscription && !$subscription->isActive()) $subscription = null;
        $records = [];
        if ($subscription) {
            $records[] = ['id' => $subscription->getId(), 'type' => 'subscription', 'title' => 'Unlimited Learning',
                'date' => ($subscription->getInvoiceDate() ?? $subscription->getStartDate())->format(DATE_ATOM),
                'endDate' => $subscription->getEndDate()->format(DATE_ATOM), 'amountCents' => $subscription->getInvoiceAmountCents() ?? 2990,
                'currency' => strtoupper($subscription->getInvoiceCurrency() ?: 'EUR'), 'invoiceNumber' => $subscription->getInvoiceNumber(),
                'invoiceUrl' => $this->urls->generate('app_invoice_subscription_download')];
        }
        foreach ($this->enrollments->findByUserWithCourse($user) as $enrollment) {
            $course = $enrollment->getCourse();
            $amount = $enrollment->getInvoiceAmountCents();
            $currency = $enrollment->getInvoiceCurrency();
            if ($amount === null) {
                foreach ($course->getCoursePrices() as $price) {
                    if ($price->getCurrency() === 'EUR') { $amount = $price->getPrice(); $currency = 'EUR'; }
                }
            }
            $records[] = ['id' => $enrollment->getId(), 'type' => 'course', 'title' => $course->getTitle(),
                'date' => ($enrollment->getInvoiceDate() ?? $enrollment->getCreatedAt())->format(DATE_ATOM), 'amountCents' => $amount,
                'currency' => $currency ? strtoupper($currency) : null, 'invoiceNumber' => $enrollment->getInvoiceNumber(),
                'courseId' => $course->getId(), 'image' => $course->getThumb(), 'category' => $course->getCategory()?->getName(),
                'invoiceUrl' => $this->urls->generate('app_invoice_enrollment_download', ['id' => $enrollment->getId()])];
        }
        return ['hasSubscription' => $subscription !== null, 'hasActiveSubscription' => $user->hasActiveSubscription(), 'records' => $records,
            'totalSpendEurCents' => array_sum(array_map(static fn (array $record): int => $record['currency'] === 'EUR' ? ($record['amountCents'] ?? 0) : 0, $records)),
            'plan' => ['monthlyAmountCents' => 3990, 'currency' => 'EUR', 'commitmentMonths' => 12, 'taxIncluded' => false]];
    }
}
