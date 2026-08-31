<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AvatarStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'avatar-storage-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testItStoresValidatedAvatarAndRemovesPreviousFile(): void
    {
        $oldFilename = 'previous-avatar.png';
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.$oldFilename, 'old');

        $source = $this->directory.DIRECTORY_SEPARATOR.'source.png';
        file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $storage = new AvatarStorage($entityManager, $this->directory);
        $user = (new User())->setAvatar($oldFilename);

        $storage->save($user, new UploadedFile($source, 'profile.png', 'image/png', UPLOAD_ERR_OK, true));

        self::assertNotNull($user->getAvatar());
        self::assertStringStartsWith('avatar-', $user->getAvatar());
        self::assertFileExists($this->directory.DIRECTORY_SEPARATOR.$user->getAvatar());
        self::assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.$oldFilename);
    }
}
