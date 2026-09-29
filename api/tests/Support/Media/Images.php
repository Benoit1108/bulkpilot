<?php

declare(strict_types=1);

namespace App\Tests\Support\Media;

/**
 * Images written for a test, in the system's temporary directory (removed by `clean()`).
 */
final class Images
{
    /** @var list<string> */
    private static array $files = [];

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function jpeg(int $width, int $height, ?int $orientation = null, bool $gps = false): string
    {
        $image = self::canvas($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        if (null !== $orientation || $gps) {
            // An APP1 Exif segment right after SOI, as a phone writes it.
            $jpeg = substr($jpeg, 0, 2).self::exif($orientation ?? 1, $gps).substr($jpeg, 2);
        }

        return self::write($jpeg, 'jpg');
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function png(int $width, int $height, bool $animated = false): string
    {
        $image = self::canvas($width, $height);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        if ($animated) {
            // An acTL chunk before the image data makes it an APNG.
            $chunk = pack('N', 8).'acTL'.pack('NN', 2, 0);
            $png = substr($png, 0, 33).$chunk.pack('N', crc32('acTL'.pack('NN', 2, 0))).substr($png, 33);
        }

        return self::write($png, 'png');
    }

    public static function file(string $contents, string $extension): string
    {
        return self::write($contents, $extension);
    }

    public static function clean(): void
    {
        foreach (self::$files as $file) {
            @unlink($file);
        }
        self::$files = [];
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private static function canvas(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        if (false === $image) {
            throw new \RuntimeException('GD cannot create an image.');
        }

        return $image;
    }

    private static function exif(int $orientation, bool $gps): string
    {
        $entries = [pack('vvVvv', 0x0112, 3, 1, $orientation, 0)];
        if ($gps) {
            // A GPS IFD pointer: where the photo was taken, which must never be kept.
            $entries[] = pack('vvVV', 0x8825, 4, 1, 26);
        }
        $ifd = pack('v', \count($entries)).implode('', $entries).pack('V', 0);
        $tiff = 'II'.pack('vV', 42, 8).$ifd.($gps ? pack('v', 1).pack('vvVV', 0x0002, 5, 3, 0).pack('V', 0) : '');
        $data = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', \strlen($data) + 2).$data;
    }

    private static function write(string $contents, string $extension): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'photo-'.bin2hex(random_bytes(6)).'.'.$extension;
        file_put_contents($path, $contents);
        self::$files[] = $path;

        return $path;
    }
}
