<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Monitoring;

use App\System\Application\Monitoring\Beats;
use App\System\Domain\Monitoring\Beat;
use Doctrine\DBAL\Connection;

/**
 * One row per beat (`system_heartbeats`), written by an upsert outside any unit of work: the
 * worker beats while it may be inside a job, and nothing else is ever written with it.
 */
final readonly class DoctrineBeats implements Beats
{
    public function __construct(private Connection $connection)
    {
    }

    public function record(Beat $beat, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO system_heartbeats (beat, beat_at, first_beat_at) VALUES (:beat, :at, :at) ON CONFLICT (beat) DO UPDATE SET beat_at = EXCLUDED.beat_at',
            ['beat' => $beat->value, 'at' => $at],
            ['at' => 'datetimetz_immutable'],
        );
    }

    public function lastOf(Beat $beat): ?\DateTimeImmutable
    {
        return $this->read('beat_at', $beat);
    }

    public function firstOf(Beat $beat): ?\DateTimeImmutable
    {
        return $this->read('first_beat_at', $beat);
    }

    /**
     * @param 'beat_at'|'first_beat_at' $column
     */
    private function read(string $column, Beat $beat): ?\DateTimeImmutable
    {
        $at = $this->connection->fetchOne(\sprintf('SELECT %s FROM system_heartbeats WHERE beat = :beat', $column), ['beat' => $beat->value]);

        return \is_string($at) ? new \DateTimeImmutable($at) : null;
    }
}
