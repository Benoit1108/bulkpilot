<?php

declare(strict_types=1);

namespace App\System\Domain\Backup;

/**
 * Which backups to delete (spec §106: configurable retention): those older than the retention,
 * never the latest one — a restore is always possible, even after backups stopped for a while.
 */
final readonly class Retention
{
    public const int MIN_DAYS = 1;

    public function __construct(public int $days)
    {
        if ($days < self::MIN_DAYS) {
            throw new \InvalidArgumentException('Backups are kept at least a day.');
        }
    }

    /**
     * @param list<BackupKey> $backups
     *
     * @return list<BackupKey> the ones to delete
     */
    public function expired(array $backups, \DateTimeImmutable $now): array
    {
        $latest = self::latest($backups);
        $limit = $now->modify(\sprintf('-%d days', $this->days));

        return array_values(array_filter(
            $backups,
            static fn (BackupKey $backup): bool => $backup->takenAt < $limit && $backup->value !== $latest?->value,
        ));
    }

    /**
     * @param list<BackupKey> $backups
     */
    public static function latest(array $backups): ?BackupKey
    {
        $latest = null;
        foreach ($backups as $backup) {
            if (null === $latest || $backup->takenAt > $latest->takenAt) {
                $latest = $backup;
            }
        }

        return $latest;
    }
}
