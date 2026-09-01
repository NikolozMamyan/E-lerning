<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CommunityEvent;
use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Service\AdminCommunityService;
use App\Service\JobApplicationCvStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AdminCommunityServiceTest extends TestCase
{
    private string $cvDirectory;

    protected function setUp(): void
    {
        $this->cvDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'admin-community-'.bin2hex(random_bytes(8));
        mkdir($this->cvDirectory, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cvDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->cvDirectory)) {
            rmdir($this->cvDirectory);
        }
    }

    public function testDeletingJobOfferRemovesEntityAndStoredApplicationCvs(): void
    {
        $filename = 'cv-test.pdf';
        file_put_contents($this->cvDirectory.DIRECTORY_SEPARATOR.$filename, 'test CV');

        $jobOffer = new JobOffer();
        $application = (new JobApplication())->setJobOffer($jobOffer)->setCvFilename($filename);
        $jobOffer->getApplications()->add($application);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($jobOffer);
        $entityManager->expects(self::once())->method('flush');

        $service = new AdminCommunityService($entityManager, new JobApplicationCvStorage($this->cvDirectory));
        $service->deleteJobOffer($jobOffer);

        self::assertFileDoesNotExist($this->cvDirectory.DIRECTORY_SEPARATOR.$filename);
    }

    public function testDeletingEventRemovesAndFlushesEntity(): void
    {
        $event = new CommunityEvent();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($event);
        $entityManager->expects(self::once())->method('flush');

        $service = new AdminCommunityService($entityManager, new JobApplicationCvStorage($this->cvDirectory));
        $service->deleteEvent($event);
    }

    public function testUpdatesFlushManagedEntities(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $service = new AdminCommunityService($entityManager, new JobApplicationCvStorage($this->cvDirectory));
        $service->updateJobOffer();
        $service->updateEvent();
    }
}
