<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Shared\Application\Coaching\DayIntake;
use App\Shared\Application\Coaching\NutritionReadings;
use App\Shared\Application\Coaching\TargetReading;
use App\Shared\Domain\Identifier\UserId;

/**
 * One user's target and days logged (nothing for anyone else); `intake` keeps the days of the range asked.
 */
final class FakeNutritionReadings implements NutritionReadings
{
    public ?TargetReading $target = null;
    /** @var list<DayIntake> the oldest first */
    public array $days = [];

    public function __construct(private readonly string $owner)
    {
    }

    /**
     * @param array<string, array{float, float}> $days kcal and protein by day
     */
    public function log(array $days): void
    {
        foreach ($days as $day => [$kcal, $protein]) {
            $this->days[] = new DayIntake(new \DateTimeImmutable($day), $kcal, $protein);
        }
        usort($this->days, static fn (DayIntake $a, DayIntake $b): int => $a->day <=> $b->day);
    }

    public function intake(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($userId->value !== $this->owner) {
            return [];
        }

        return array_values(array_filter(
            $this->days,
            static fn (DayIntake $day): bool => $day->day >= $from->setTime(0, 0) && $day->day <= $to->setTime(0, 0),
        ));
    }

    public function targetOn(UserId $userId, \DateTimeImmutable $day): ?TargetReading
    {
        return $userId->value === $this->owner && null !== $this->target && $this->target->effectiveFrom <= $day->setTime(0, 0) ? $this->target : null;
    }
}
