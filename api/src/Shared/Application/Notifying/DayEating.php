<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

final readonly class DayEating
{
    public function __construct(
        public float $eatenKcal,
        /** The target in force that day, null without one. */
        public ?int $targetKcal,
        public ?\DateTimeImmutable $lastLoggedAt,
    ) {
    }
}
