<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\CommunityEvent;
use App\Entity\JobOffer;
use App\Form\CommunityEventType;
use App\Form\JobOfferType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class CommunityRichTextFormTest extends KernelTestCase
{
    public function testJobDescriptionKeepsFormattingAndRemovesUnsafeHtml(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $jobOffer = new JobOffer();
        $form = $formFactory->create(JobOfferType::class, $jobOffer, ['csrf_protection' => false]);

        $form->submit([
            'title' => 'Compliance Officer',
            'description' => '<h2>Role</h2><p><strong>A sufficiently long description.</strong><script>alert(1)</script> <a href="javascript:alert(1)">Unsafe</a> <a href="https://example.com/jobs">Apply</a></p>',
            'isActive' => '1',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertStringContainsString('<h2>Role</h2>', (string) $jobOffer->getDescription());
        self::assertStringContainsString('<strong>A sufficiently long description.</strong>', (string) $jobOffer->getDescription());
        self::assertStringNotContainsString('<script', (string) $jobOffer->getDescription());
        self::assertStringNotContainsString('javascript:', (string) $jobOffer->getDescription());
        self::assertStringContainsString('rel="noopener noreferrer"', (string) $jobOffer->getDescription());
    }

    public function testEventDescriptionUsesTheSameSanitizedFormatting(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $event = new CommunityEvent();
        $form = $formFactory->create(CommunityEventType::class, $event, ['csrf_protection' => false]);

        $form->submit([
            'title' => 'Regulatory Breakfast',
            'description' => '<p>Programme details for the community.</p><ul><li>Welcome</li><li><em>Workshop</em></li></ul><img src=x onerror=alert(1)>',
            'startsAt' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d\TH:i'),
            'location' => 'Luxembourg',
            'isPublished' => '1',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertStringContainsString('<ul><li>Welcome</li><li><em>Workshop</em></li></ul>', (string) $event->getDescription());
        self::assertStringNotContainsString('<img', (string) $event->getDescription());
        self::assertStringNotContainsString('onerror', (string) $event->getDescription());
    }
}
