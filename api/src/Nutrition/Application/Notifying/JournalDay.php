<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Notifying;

use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Notifying\DayEating;
use App\Shared\Application\Notifying\EatingReadings;
use App\Shared\Domain\Identifier\UserId;

/**
 * A day of the journal as the notifications read it (ADR-0029).
 */
final readonly class JournalDay implements EatingReadings
{
    public function __construct(private JournalEntryRepository $entries, private NutritionTargetRepository $targets)
    {
    }

    public function day(UserId $userId, \DateTimeImmutable $day): DayEating
    {
        $day = $day->setTime(0, 0);
        $eaten = 0.0;
        $last = null;
        foreach ($this->entries->ofDay($userId, $day) as $entry) {
            $eaten += $entry->amounts()->energyKcal;
            $last = null === $last ? $entry->loggedAt() : max($last, $entry->loggedAt());
        }

        return new DayEating($eaten, $this->targets->inForceOn($userId, $day)?->targets()->energyKcal, $last);
    }
}
