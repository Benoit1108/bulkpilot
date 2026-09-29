<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\Application\PhotoLibrary;
use App\Media\Infrastructure\Image\GdImageEncoder;
use App\Media\Infrastructure\Storage\LocalMediaStorage;
use App\Media\Infrastructure\Storage\MediaStorageFactory;
use App\Media\Infrastructure\Storage\S3MediaStorage;
use App\Media\Infrastructure\Storage\SignedLocalUrls;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Media\Images;
use App\Tests\Support\Media\InMemoryMediaFileRepository;
use App\Tests\Support\Media\InMemoryMediaStorage;
use AsyncAws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StorageTest extends TestCase
{
    private const string PHOTO = '00000000-0000-7000-8000-00000000f001';

    protected function tearDown(): void
    {
        Images::clean();
    }

    public function testAPhotoWhoseThumbnailCannotBeWrittenLeavesNothing(): void
    {
        $storage = new InMemoryMediaStorage();
        $storage->failingOn = 'thumbnail';
        $files = new InMemoryMediaFileRepository();
        $library = new PhotoLibrary($files, $storage, new GdImageEncoder(), new MockClock());

        try {
            $library->store(new UserId('00000000-0000-7000-8000-00000000a1e7'), new MediaId(self::PHOTO), Images::jpeg(80, 60));
            self::fail('stored');
        } catch (\RuntimeException $failure) {
            self::assertSame('bucket unreachable', $failure->getMessage());
        }
        self::assertSame([[], []], [$storage->files, $files->files]);
    }

    public function testForgettingRemovesWhatWasWrittenWithoutARecord(): void
    {
        $storage = new InMemoryMediaStorage();
        foreach ([self::PHOTO.'.webp', self::PHOTO.'-thumbnail.webp', 'other.webp'] as $key) {
            $storage->write($key, 'webp', 'image/webp');
        }
        $library = new PhotoLibrary(new InMemoryMediaFileRepository(), $storage, new GdImageEncoder(), new MockClock());

        $library->forget(new MediaId(self::PHOTO));

        self::assertSame(['other.webp'], array_keys($storage->files));
    }

    public function testS3WritesWithTheTypeAndSignsForTheBrowsersHost(): void
    {
        $sent = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $headers = $options['headers'] ?? [];
            $sent[] = [$method, $url, \is_array($headers) ? implode("\n", array_filter($headers, \is_string(...))) : ''];

            return new MockResponse('', ['http_code' => 200]);
        });
        $client = static fn (string $endpoint): S3Client => new S3Client(['endpoint' => $endpoint, 'region' => 'us-east-1', 'accessKeyId' => 'key', 'accessKeySecret' => 'secret', 'pathStyleEndpoint' => 'true'], null, $http);
        $storage = new S3MediaStorage($client('http://s3:7070'), $client('https://photos.example'), 'bucket');

        $storage->write(self::PHOTO.'.webp', 'webp', 'image/webp');
        $storage->delete(self::PHOTO.'.webp');
        $url = $storage->temporaryUrl(self::PHOTO.'.webp', new \DateTimeImmutable('+5 minutes'), 'photo-2026-09-29-front.webp');

        self::assertSame(['PUT', 'http://s3:7070/bucket/'.self::PHOTO.'.webp'], [$sent[0][0], $sent[0][1]]);
        self::assertStringContainsStringIgnoringCase('content-type: image/webp', $sent[0][2]);
        self::assertSame('DELETE', $sent[1][0]);
        self::assertStringStartsWith('https://photos.example/bucket/'.self::PHOTO.'.webp?', $url, 'signed for the host the browser reaches');
        self::assertStringContainsString('response-cache-control=private%2C%20no-store', $url);
        self::assertStringContainsString('response-content-disposition=attachment%3B%20filename%3D%22photo-2026-09-29-front.webp%22', $url);
        self::assertStringContainsString('X-Amz-Expires=', $url);
        self::assertStringNotContainsString('response-content-disposition', $storage->temporaryUrl(self::PHOTO.'.webp', new \DateTimeImmutable('+5 minutes')));
    }

    public function testTheStorageIsTheOneConfigured(): void
    {
        $local = new LocalMediaStorage(sys_get_temp_dir(), new SignedLocalUrls('secret'));
        $factory = new MediaStorageFactory($local, '', '', 'us-east-1', 'bucket', 'key', 'secret', false);

        self::assertSame($local, $factory->create('local'));
        self::assertInstanceOf(S3MediaStorage::class, $factory->create('s3'));
        $this->expectException(\InvalidArgumentException::class);
        $factory->create('disk');
    }

    public function testALocalFileKeyIsAPhotosKeyOnly(): void
    {
        $local = new LocalMediaStorage(sys_get_temp_dir(), new SignedLocalUrls('secret'));

        self::assertStringEndsWith(self::PHOTO.'-thumbnail.webp', $local->path(self::PHOTO.'-thumbnail.webp'));
        $this->expectException(\InvalidArgumentException::class);
        $local->path('../../etc/passwd');
    }

    public function testALinkSignsItsDownloadName(): void
    {
        $urls = new SignedLocalUrls('secret');
        $signature = $urls->sign(self::PHOTO.'.webp', 2_000_000_000, 'photo.webp');

        self::assertFalse($urls->verifies(self::PHOTO.'.webp', 2_000_000_000, 'other.webp', $signature, new \DateTimeImmutable('2026-09-29')));
        self::assertTrue($urls->verifies(self::PHOTO.'.webp', 2_000_000_000, 'photo.webp', $signature, new \DateTimeImmutable('2026-09-29')));
    }
}
