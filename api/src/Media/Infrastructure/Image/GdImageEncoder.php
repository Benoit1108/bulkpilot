<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Image;

use App\Media\Application\EncodedImage;
use App\Media\Application\ImageEncoder;
use App\Shared\Application\Media\InvalidPhoto;

/**
 * JPEG, PNG or WebP in, WebP out (ADR-0027): the real type is read from the first bytes, the size
 * and pixel count are bounded before decoding, animations are refused. Decoding then re-encoding
 * drops every metadata (EXIF, GPS, XMP); the EXIF orientation is applied first. GD rather than
 * ImageMagick: far fewer decoders to trust.
 */
final class GdImageEncoder implements ImageEncoder
{
    public const int MAX_BYTES = 15 * 1024 * 1024;
    public const int MAX_SIDE = 8000;
    public const int MAX_PIXELS = 24_000_000;
    public const int LONGEST_SIDE = 2048;
    public const int THUMBNAIL_SIDE = 480;
    private const int QUALITY = 82;
    private const int THUMBNAIL_QUALITY = 75;

    public function encode(string $path): EncodedImage
    {
        $size = @filesize($path);
        if (false === $size || 0 === $size || $size > self::MAX_BYTES) {
            throw InvalidPhoto::because(\sprintf('a photo weighs at most %d MB', self::MAX_BYTES / 1024 / 1024));
        }
        $head = (string) file_get_contents($path, length: 64);
        $type = self::type($head);
        $info = @getimagesize($path);
        if (null === $type || false === $info || $info[2] !== $type) {
            throw InvalidPhoto::because('a photo is a JPEG, PNG or WebP image');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw InvalidPhoto::because(\sprintf('a photo is at most %d pixels a side and %d megapixels', self::MAX_SIDE, self::MAX_PIXELS / 1_000_000));
        }
        if (self::animated($path, $type)) {
            throw InvalidPhoto::because('a photo is a still image');
        }

        $image = match ($type) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };
        if (false === $image) {
            throw InvalidPhoto::because('the image cannot be read');
        }
        // Resized before it is turned: a rotation copies the bitmap, done on 2048 px rather than 24 Mpx.
        $full = self::fitted($image, self::LONGEST_SIDE);
        unset($image);
        if (\IMAGETYPE_JPEG === $type) {
            $full = self::oriented($full, $path);
        }
        $thumbnail = self::fitted($full, self::THUMBNAIL_SIDE);

        return new EncodedImage(self::webp($full, self::QUALITY), self::webp($thumbnail, self::THUMBNAIL_QUALITY), imagesx($full), imagesy($full));
    }

    private static function type(string $head): ?int
    {
        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => \IMAGETYPE_JPEG,
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => \IMAGETYPE_PNG,
            str_starts_with($head, 'RIFF') && 'WEBP' === substr($head, 8, 4) => \IMAGETYPE_WEBP,
            default => null,
        };
    }

    /** An animated WebP (VP8X animation flag) or PNG (an acTL chunk before the image data): refused, not flattened. */
    private static function animated(string $path, int $type): bool
    {
        if (\IMAGETYPE_WEBP === $type) {
            $head = (string) file_get_contents($path, length: 21);

            return 'VP8X' === substr($head, 12, 4) && 0 !== (\ord($head[20] ?? "\0") & 0x02);
        }
        if (\IMAGETYPE_PNG !== $type) {
            return false;
        }
        // Chunks one after the other (length, type, data, CRC) from byte 8, up to the image data.
        $file = fopen($path, 'r');
        if (false === $file) {
            return false;
        }
        try {
            fseek($file, 8);
            while (8 === \strlen($header = (string) fread($file, 8))) {
                $length = unpack('N', substr($header, 0, 4));
                $chunk = substr($header, 4, 4);
                if ('acTL' === $chunk) {
                    return true;
                }
                if ('IDAT' === $chunk || !\is_array($length) || !\is_int($length[1] ?? null) || 0 !== fseek($file, $length[1] + 4, \SEEK_CUR)) {
                    return false;
                }
            }

            return false;
        } finally {
            fclose($file);
        }
    }

    /** Phones store the picture sideways with an EXIF orientation: it is applied, then dropped with the rest. */
    private static function oriented(\GdImage $image, string $path): \GdImage
    {
        $exif = \function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = \is_array($exif) && \is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, \in_array($orientation, [2, 5], true) ? \IMG_FLIP_HORIZONTAL : \IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if (0 === $angle) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return false === $rotated ? $image : $rotated;
    }

    private static function fitted(\GdImage $image, int $side): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if (max($width, $height) <= $side) {
            return $image;
        }
        $scale = $side / max($width, $height);
        $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), \IMG_BICUBIC);
        if (false === $scaled) {
            throw InvalidPhoto::because('the image cannot be resized');
        }

        return $scaled;
    }

    private static function webp(\GdImage $image, int $quality): string
    {
        ob_start();
        $written = imagewebp($image, null, $quality);
        $bytes = (string) ob_get_clean();
        if (!$written || '' === $bytes) {
            throw InvalidPhoto::because('the image cannot be encoded');
        }

        return $bytes;
    }
}
