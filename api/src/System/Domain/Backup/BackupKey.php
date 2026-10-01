<?php

declare(strict_types=1);

namespace App\System\Domain\Backup;

/**
 * Where a database backup is kept in the bucket: `db/2026/09/30/023000.dump.enc`, by the time it
 * was taken (UTC), so that listing the prefix sorts them by date.
 */
final readonly class BackupKey
{
    public const string PREFIX = 'db/';
    private const string PATTERN = '#^db/(\d{4})/(\d{2})/(\d{2})/(\d{2})(\d{2})(\d{2})\.dump\.enc$#';

    private function __construct(public string $value, public \DateTimeImmutable $takenAt)
    {
    }

    public static function at(\DateTimeImmutable $takenAt): self
    {
        $utc = $takenAt->setTimezone(new \DateTimeZone('UTC'));

        return new self(self::PREFIX.$utc->format('Y/m/d/His').'.dump.enc', $utc);
    }

    /** Null for anything else found under the prefix (never deleted, never restored). */
    public static function parse(string $key): ?self
    {
        if (1 !== preg_match(self::PATTERN, $key, $parts)) {
            return null;
        }
        $takenAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', \sprintf('%s-%s-%s %s:%s:%s', ...\array_slice($parts, 1)), new \DateTimeZone('UTC'));

        // A date that does not exist (2026-13-45) is not rolled over into another one.
        return false === $takenAt || self::at($takenAt)->value !== $key ? null : new self($key, $takenAt);
    }
}
