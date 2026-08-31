<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompanyCourseCheckoutService
{
    private const RECIPIENT_CHUNK_LENGTH = 450;
    private const MAX_RECIPIENT_CHUNKS = 40;

    public function __construct(
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param mixed[] $requestedRecipientIds
     */
    public function createCheckout(Course $course, User $company, array $requestedRecipientIds): Session
    {
        $recipientIds = $this->resolveBillableRecipientIds($course, $company, $requestedRecipientIds);
        $quantity = count($recipientIds);
        $unitAmount = $this->euroPrice($course);

        $this->configureStripe();

        return Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $company->getEmail(),
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $unitAmount,
                    'product_data' => ['name' => $course->getTitle()],
                ],
                'quantity' => $quantity,
            ]],
            'metadata' => array_merge([
                'context' => 'company_bulk_seats',
                'company_user_id' => (string) $company->getId(),
                'course_id' => (string) $course->getId(),
                'quantity' => (string) $quantity,
            ], $this->encodeRecipientIds($recipientIds)),
            'success_url' => $this->urlGenerator->generate('company_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                .'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->urlGenerator->generate('company_payment_cancel', ['c' => $course->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    /**
     * @return int[]
     */
    public function recipientIdsFromMetadata(object|array $metadata): array
    {
        $chunkCount = (int) $this->metadataValue($metadata, 'recipient_chunk_count');
        if ($chunkCount < 1 || $chunkCount > self::MAX_RECIPIENT_CHUNKS) {
            return [];
        }

        $encodedChunks = [];
        for ($index = 1; $index <= $chunkCount; $index++) {
            $encodedChunks[] = $this->metadataValue($metadata, 'recipient_ids_'.$index);
        }
        $encodedIds = implode(',', $encodedChunks);

        $ids = [];
        foreach (explode(',', $encodedIds) as $encodedId) {
            if ($encodedId !== '' && ctype_digit($encodedId)) {
                $ids[] = (int) $encodedId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed[] $requestedRecipientIds
     * @return int[]
     */
    private function resolveBillableRecipientIds(Course $course, User $company, array $requestedRecipientIds): array
    {
        if (!in_array('ROLE_COMPANY', $company->getRoles(), true)) {
            throw new \DomainException('Only a company account can purchase team seats.');
        }

        $requestedIds = [];
        foreach ($requestedRecipientIds as $requestedId) {
            $value = is_int($requestedId) || is_string($requestedId) ? (string) $requestedId : '';
            if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
                $requestedIds[] = (int) $value;
            }
        }
        $requestedIds = array_values(array_unique($requestedIds));

        if ($requestedIds === []) {
            throw new \DomainException('Select at least one team member.');
        }

        $teamMemberIds = [];
        foreach ($company->getCollaborationsAsCompany() as $collaboration) {
            $employeeId = $collaboration->getEmployee()?->getId();
            if ($employeeId !== null) {
                $teamMemberIds[$employeeId] = true;
            }
        }

        foreach ($requestedIds as $requestedId) {
            if (!isset($teamMemberIds[$requestedId])) {
                throw new \DomainException('The employee selection contains an invalid team member.');
            }
        }

        $alreadyEnrolledIds = array_flip($this->enrollmentRepository->findEnrolledUserIdsForCourse($course, $requestedIds));
        $billableIds = array_values(array_filter(
            $requestedIds,
            static fn (int $recipientId): bool => !isset($alreadyEnrolledIds[$recipientId]),
        ));

        if ($billableIds === []) {
            throw new \DomainException('All selected team members already have access to this course.');
        }

        return $billableIds;
    }

    /**
     * @param int[] $recipientIds
     * @return array<string, string>
     */
    private function encodeRecipientIds(array $recipientIds): array
    {
        $chunks = [];
        $currentChunk = '';

        foreach ($recipientIds as $recipientId) {
            $encodedId = (string) $recipientId;
            $candidate = $currentChunk === '' ? $encodedId : $currentChunk.','.$encodedId;

            if (strlen($candidate) > self::RECIPIENT_CHUNK_LENGTH) {
                $chunks[] = $currentChunk;
                $currentChunk = $encodedId;
            } else {
                $currentChunk = $candidate;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        if (count($chunks) > self::MAX_RECIPIENT_CHUNKS) {
            throw new \DomainException('Too many team members were selected for one checkout.');
        }

        $metadata = ['recipient_chunk_count' => (string) count($chunks)];
        foreach ($chunks as $index => $chunk) {
            $metadata['recipient_ids_'.($index + 1)] = $chunk;
        }

        return $metadata;
    }

    private function euroPrice(Course $course): int
    {
        foreach ($course->getCoursePrices() as $price) {
            if (strtoupper((string) $price->getCurrency()) === 'EUR' && $price->getPrice() > 0) {
                return $price->getPrice();
            }
        }

        throw new \DomainException('No EUR price is available for this course.');
    }

    private function metadataValue(object|array $metadata, string $key): string
    {
        if (is_array($metadata)) {
            return isset($metadata[$key]) ? (string) $metadata[$key] : '';
        }

        return isset($metadata->{$key}) ? (string) $metadata->{$key} : '';
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
