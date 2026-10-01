<?php

declare(strict_types=1);

namespace App\System\Domain\Backup;

/**
 * An account erased since some backups were taken (`erasures/2026-10-07/<uuid>`): a database
 * restored from one of them must erase it again, or the account would come back. Kept a while
 * longer than the backups it concerns, then deleted.
 */
final readonly class ErasureMarker
{
    public const string PREFIX = 'erasures/';
    /** Beyond the backups' retention: the last backup that still holds the account has gone too. */
    public const int EXTRA_DAYS = 7;
    private const string PATTERN = '#^erasures/(\d{4}-\d{2}-\d{2})/([0-9a-f-]{36})$#';

    private function __construct(public string $value, public string $userId, public \DateTimeImmutable $erasedOn)
    {
    }

    public static function of(string $userId, \DateTimeImmutable $erasedAt): self
    {
        $day = $erasedAt->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        return new self(self::PREFIX.$day->format('Y-m-d').'/'.$userId, $userId, $day);
    }

    public static function parse(string $key): ?self
    {
        if (1 !== preg_match(self::PATTERN, $key, $parts)) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $parts[1], new \DateTimeZone('UTC'));

        return false === $day || $day->format('Y-m-d') !== $parts[1] ? null : new self($key, $parts[2], $day);
    }

    /** Past the retention of every backup that may hold the account. */
    public function isObsolete(Retention $retention, \DateTimeImmutable $now): bool
    {
        return $this->erasedOn < $now->modify(\sprintf('-%d days', $retention->days + self::EXTRA_DAYS));
    }
}
