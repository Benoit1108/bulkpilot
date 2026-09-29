<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

/**
 * An exercise still trained that has not beaten a record for a while (spec §67).
 */
final readonly class Stall
{
    public function __construct(public string $exercise, public int $sessions, public \DateTimeImmutable $since)
    {
    }
}
