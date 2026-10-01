<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\Application\ArchiveLibrary;
use App\Media\Application\PersonalData\DeleteErasedFiles;
use App\Media\Application\PersonalData\MediaPersonalData;
use App\Media\Application\PhotoLibrary;
use App\Media\Domain\MediaFileErased;
use App\Media\Infrastructure\Image\GdImageEncoder;
use App\Shared\Application\Media\MediaNotFound;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Media\Images;
use App\Tests\Support\Media\InMemoryMediaFileRepository;
use App\Tests\Support\Media\InMemoryMediaStorage;
use App\Tests\Support\Shared\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Archives kept like photos, photos copied into an export, and every file of an account erased
 * after the commit (ADR-0030).
 */
final class ArchivesAndErasureTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string SAM = '00000000-0000-7000-8000-000000000511';
    private const string ARCHIVE = '00000000-0000-7000-8000-00000000a001';
    private const string PHOTO = '00000000-0000-7000-8000-00000000f001';

    private InMemoryMediaStorage $storage;
    private InMemoryMediaFileRepository $files;
    private MockClock $clock;
    private ArchiveLibrary $archives;
    private PhotoLibrary $photos;

    protected function setUp(): void
    {
        $this->storage = new InMemoryMediaStorage();
        $this->files = new InMemoryMediaFileRepository();
        $this->clock = new MockClock('2026-09-30 10:00:00');
        $this->archives = new ArchiveLibrary($this->files, $this->storage, $this->clock);
        $this->photos = new PhotoLibrary($this->files, $this->storage, new GdImageEncoder(), $this->clock);
    }

    protected function tearDown(): void
    {
        Images::clean();
    }

    public function testAnArchiveIsKeptUnderAnOpaqueKeyAndLinkedToItsOwnerOnly(): void
    {
        $this->archives->store(new UserId(self::ALEX), new MediaId(self::ARCHIVE), $this->zip('PK…'), 'application/zip');

        self::assertSame([self::ARCHIVE.'.zip' => 'PK…'], $this->storage->files);
        $file = $this->files->files[self::ARCHIVE];
        self::assertSame(['application/zip', null, null, 5], [$file->contentType(), $file->thumbnailKey(), $file->width(), $file->bytes()]);
        self::assertSame('memory://'.self::ARCHIVE.'.zip?expires=1790762700&download=export.zip', $this->archives->downloadLink(new UserId(self::ALEX), new MediaId(self::ARCHIVE), 'export.zip', 300));

        $this->expectException(MediaNotFound::class);
        $this->archives->downloadLink(new UserId(self::SAM), new MediaId(self::ARCHIVE), 'export.zip', 300);
    }

    public function testDeletingAnArchiveIsTheOwnersOnlyAndIdempotent(): void
    {
        $this->archives->store(new UserId(self::ALEX), new MediaId(self::ARCHIVE), $this->zip('PK'), 'application/zip');

        $this->archives->delete(new UserId(self::SAM), new MediaId(self::ARCHIVE));
        self::assertArrayHasKey(self::ARCHIVE.'.zip', $this->storage->files);
        $this->archives->delete(new UserId(self::ALEX), new MediaId(self::ARCHIVE));
        $this->archives->delete(new UserId(self::ALEX), new MediaId(self::ARCHIVE));
        self::assertSame([], $this->storage->files);
        self::assertSame([], $this->files->files);
    }

    public function testAFailedWriteLeavesNoArchive(): void
    {
        $this->storage->failingOn = '.zip';
        try {
            $this->archives->store(new UserId(self::ALEX), new MediaId(self::ARCHIVE), $this->zip('PK'), 'application/zip');
            self::fail('the write failed');
        } catch (\RuntimeException) {
        }
        self::assertSame([], $this->files->files);
    }

    public function testAPhotoIsCopiedForItsOwnerOnly(): void
    {
        $this->photos->store(new UserId(self::ALEX), new MediaId(self::PHOTO), Images::png(40, 60));
        $copy = (string) tempnam(sys_get_temp_dir(), 'copy-');

        $this->photos->copyTo(new UserId(self::ALEX), new MediaId(self::PHOTO), $copy);
        self::assertSame($this->storage->files[self::PHOTO.'.webp'], file_get_contents($copy), 'the stored image, re-encoded, not an original');
        unlink($copy);

        $this->expectException(MediaNotFound::class);
        $this->photos->copyTo(new UserId(self::SAM), new MediaId(self::PHOTO), $copy);
    }

    public function testAnErasedAccountLosesEveryFileOnceTheErasureCommitted(): void
    {
        $this->photos->store(new UserId(self::ALEX), new MediaId(self::PHOTO), Images::png(40, 60));
        $this->archives->store(new UserId(self::ALEX), new MediaId(self::ARCHIVE), $this->zip('PK'), 'application/zip');
        $this->archives->store(new UserId(self::SAM), new MediaId('00000000-0000-7000-8000-00000000a002'), $this->zip('PK'), 'application/zip');
        $events = new SpyEventBus();

        new MediaPersonalData($this->files, $events, $this->clock)->erase(new UserId(self::ALEX));

        self::assertSame(['00000000-0000-7000-8000-00000000a002'], array_keys($this->files->files), 'the records go at once');
        self::assertCount(4, $this->storage->files, 'the files stay until the erasure committed');
        $delete = new DeleteErasedFiles($this->storage);
        foreach ($events->published as $event) {
            self::assertInstanceOf(MediaFileErased::class, $event);
            $delete($event);
        }
        self::assertSame(['00000000-0000-7000-8000-00000000a002.zip'], array_keys($this->storage->files));
    }

    private function zip(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'zip-');
        file_put_contents($path, $contents);
        register_shutdown_function(static fn () => @unlink($path));

        return $path;
    }
}
