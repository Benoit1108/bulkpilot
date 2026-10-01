<?php

declare(strict_types=1);

namespace App\Shared\Application\File;

/**
 * Working files (an export being written, a dump being encrypted): private to the process user,
 * named after what they hold so that a run killed half-way (out of memory, restart) leaves files
 * the next run of the same job can find and remove.
 */
final class TemporaryFile
{
    /** A left-over file older than this belongs to a run that died. */
    public const int STALE_SECONDS = 3600;

    public static function create(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if (false === $path) {
            throw new \RuntimeException('Cannot create a temporary file.');
        }
        @chmod($path, 0o600);

        return $path;
    }

    /**
     * Removes the files a previous run with that prefix left behind.
     */
    public static function removeStale(string $prefix, \DateTimeImmutable $now): void
    {
        $paths = glob(sys_get_temp_dir().\DIRECTORY_SEPARATOR.$prefix.'*');
        foreach (false === $paths ? [] : $paths as $path) {
            $modified = @filemtime($path);
            if (false !== $modified && $modified < $now->getTimestamp() - self::STALE_SECONDS) {
                @unlink($path);
            }
        }
    }

    public static function remove(string ...$paths): void
    {
        foreach ($paths as $path) {
            @unlink($path);
        }
    }
}
