<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\Infrastructure\Image\GdImageEncoder;
use App\Shared\Application\Media\InvalidPhoto;
use App\Tests\Support\Media\Images;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GdImageEncoderTest extends TestCase
{
    protected function tearDown(): void
    {
        Images::clean();
    }

    public function testAPhotoIsKeptAsWebPWithinItsSizesAndWithoutItsMetadata(): void
    {
        $encoded = new GdImageEncoder()->encode(Images::jpeg(3000, 1500, gps: true));

        self::assertSame([2048, 1024], [$encoded->width, $encoded->height]);
        foreach ([$encoded->image, $encoded->thumbnail] as $webp) {
            self::assertSame(['RIFF', 'WEBP'], [substr($webp, 0, 4), substr($webp, 8, 4)]);
            self::assertStringNotContainsString('Exif', $webp);
        }
        $thumbnail = getimagesizefromstring($encoded->thumbnail);
        self::assertSame([480, 240], [$thumbnail[0] ?? null, $thumbnail[1] ?? null]);
    }

    public function testThePhonesOrientationIsApplied(): void
    {
        $encoded = new GdImageEncoder()->encode(Images::jpeg(40, 20, orientation: 6));

        self::assertSame([20, 40], [$encoded->width, $encoded->height], 'stored sideways, shown upright');
    }

    public function testASmallPngStaysItsSize(): void
    {
        $encoded = new GdImageEncoder()->encode(Images::png(300, 400));

        self::assertSame([300, 400], [$encoded->width, $encoded->height]);
    }

    public function testAPolyglotKeepsNothingButThePicture(): void
    {
        $polyglot = Images::file((string) file_get_contents(Images::jpeg(64, 48)).'<?php system($_GET["c"]); ?><script>alert(1)</script>', 'jpg');

        $encoded = new GdImageEncoder()->encode($polyglot);

        foreach ([$encoded->image, $encoded->thumbnail] as $webp) {
            self::assertStringNotContainsString('<?php', $webp);
            self::assertStringNotContainsString('<script', $webp);
        }
    }

    /**
     * @param \Closure(): string $file
     */
    #[DataProvider('refused')]
    public function testWhatIsNotAStillPhotoIsRefusedBeforeDecoding(\Closure $file, string $reason): void
    {
        try {
            new GdImageEncoder()->encode($file());
            self::fail('accepted');
        } catch (InvalidPhoto $refused) {
            self::assertStringContainsString($reason, $refused->getMessage());
            self::assertSame('media.invalid_photo', $refused->errorCode());
        }
    }

    /**
     * A PNG that only claims its size: decoding it would allocate width × height pixels.
     */
    private static function pngHeader(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IEND', '');
    }

    /**
     * @return iterable<string, array{\Closure(): string, string}>
     */
    public static function refused(): iterable
    {
        yield 'text named like a photo' => [static fn (): string => Images::file('not an image at all', 'jpg'), 'JPEG, PNG or WebP'];
        yield 'a GIF' => [static fn (): string => Images::file("GIF89a\x01\x00\x01\x00\x80\x00\x00", 'gif'), 'JPEG, PNG or WebP'];
        yield 'an empty file' => [static fn (): string => Images::file('', 'jpg'), 'at most'];
        yield 'an animated PNG' => [static fn (): string => Images::png(10, 10, animated: true), 'still image'];
        yield 'too many pixels' => [static fn (): string => Images::png(8001, 2), 'pixels a side'];
        yield 'an SVG, which can carry scripts' => [static fn (): string => Images::file('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'jpg'), 'JPEG, PNG or WebP'];
        yield 'an HTML page named like a photo' => [static fn (): string => Images::file('<!doctype html><script>alert(1)</script>', 'png'), 'JPEG, PNG or WebP'];
        yield 'a pixel bomb under the side limit' => [static fn (): string => Images::file(self::pngHeader(5000, 5000), 'png'), 'megapixels'];
        yield 'too heavy' => [static fn (): string => Images::file("\xFF\xD8\xFF".str_repeat('a', GdImageEncoder::MAX_BYTES), 'jpg'), 'at most 15 MB'];
    }
}
