<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\Application\PhotoLibrary;
use App\Media\Infrastructure\Image\GdImageEncoder;
use App\Media\Infrastructure\Storage\SignedLocalUrls;
use App\Shared\Application\Media\MediaNotFound;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Media\Images;
use App\Tests\Support\Media\InMemoryMediaFileRepository;
use App\Tests\Support\Media\InMemoryMediaStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PhotoLibraryTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string SAM = '00000000-0000-7000-8000-000000000511';
    private const string PHOTO = '00000000-0000-7000-8000-00000000f001';

    private InMemoryMediaStorage $storage;
    private InMemoryMediaFileRepository $files;
    private PhotoLibrary $library;

    protected function setUp(): void
    {
        $this->storage = new InMemoryMediaStorage();
        $this->files = new InMemoryMediaFileRepository();
        $this->library = new PhotoLibrary($this->files, $this->storage, new GdImageEncoder(), new MockClock('2026-09-29 08:00:00'));
    }

    protected function tearDown(): void
    {
        Images::clean();
    }

    public function testAPhotoIsStoredUnderOpaqueKeysWithItsThumbnail(): void
    {
        $stored = $this->library->store(new UserId(self::ALEX), new MediaId(self::PHOTO), Images::jpeg(800, 600));

        self::assertSame([800, 600], [$stored->width, $stored->height]);
        self::assertSame([self::PHOTO.'.webp', self::PHOTO.'-thumbnail.webp'], array_keys($this->storage->files));
        self::assertSame(self::ALEX, $this->files->files[self::PHOTO]->ownerId()->value);
    }

    public function testLinksExpireInFiveMinutesAndAreGivenToTheOwnerOnly(): void
    {
        $this->library->store(new UserId(self::ALEX), new MediaId(self::PHOTO), Images::jpeg(80, 60));

        $links = $this->library->links(new UserId(self::ALEX), new MediaId(self::PHOTO), 'photo-2026-09-29-front.webp');
        self::assertSame('2026-09-29 08:05:00', $links->expiresAt->format('Y-m-d H:i:s'));
        self::assertStringContainsString('-thumbnail.webp', $links->thumbnailUrl);
        self::assertStringContainsString('download=photo-2026-09-29-front.webp', $links->downloadUrl);

        $this->expectException(MediaNotFound::class);
        $this->library->links(new UserId(self::SAM), new MediaId(self::PHOTO), 'x.webp');
    }

    public function testDeletingRemovesBothFilesForTheOwnerOnlyAndTwiceChangesNothing(): void
    {
        $this->library->store(new UserId(self::ALEX), new MediaId(self::PHOTO), Images::jpeg(80, 60));

        $this->library->delete(new UserId(self::SAM), new MediaId(self::PHOTO));
        self::assertCount(2, $this->storage->files, 'someone else deletes nothing');

        $this->library->delete(new UserId(self::ALEX), new MediaId(self::PHOTO));
        $this->library->delete(new UserId(self::ALEX), new MediaId(self::PHOTO));
        self::assertSame([], $this->storage->files);
        self::assertSame([], $this->files->files);
    }

    public function testALocalLinkWorksUntilItExpiresAndOnlyAsSigned(): void
    {
        $urls = new SignedLocalUrls('app-secret');
        $expires = new \DateTimeImmutable('2026-09-29 08:05:00');
        $url = $urls->url(self::PHOTO.'.webp', $expires, 'photo.webp');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $signature = \is_string($query['signature'] ?? null) ? $query['signature'] : '';

        self::assertStringStartsWith('/api/media/'.self::PHOTO.'.webp?', $url);
        self::assertTrue($urls->verifies(self::PHOTO.'.webp', $expires->getTimestamp(), 'photo.webp', $signature, new \DateTimeImmutable('2026-09-29 08:04:59')));
        self::assertFalse($urls->verifies(self::PHOTO.'.webp', $expires->getTimestamp(), 'photo.webp', $signature, new \DateTimeImmutable('2026-09-29 08:05:01')), 'expired');
        self::assertFalse($urls->verifies(self::PHOTO.'.webp', $expires->getTimestamp() + 60, 'photo.webp', $signature, new \DateTimeImmutable('2026-09-29 08:00:00')), 'expiry changed');
        self::assertFalse($urls->verifies(self::PHOTO.'-thumbnail.webp', $expires->getTimestamp(), 'photo.webp', $signature, new \DateTimeImmutable('2026-09-29 08:00:00')), 'another file');
        self::assertFalse(new SignedLocalUrls('other-secret')->verifies(self::PHOTO.'.webp', $expires->getTimestamp(), 'photo.webp', $signature, new \DateTimeImmutable('2026-09-29 08:00:00')));
    }
}
