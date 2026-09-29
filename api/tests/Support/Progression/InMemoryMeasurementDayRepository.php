<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryMeasurementDayRepository implements MeasurementDayRepository
{
    /** @var array<string, MeasurementDay> */
    public array $days = [];

    public function save(MeasurementDay $day): void
    {
        $this->days[self::key($day->ownerId(), $day->day())] = $day;
    }

    public function remove(MeasurementDay $day): void
    {
        unset($this->days[self::key($day->ownerId(), $day->day())]);
    }

    public function on(UserId $userId, \DateTimeImmutable $day): ?MeasurementDay
    {
        return $this->days[self::key($userId, $day)] ?? null;
    }

    public function ofUser(UserId $userId): array
    {
        $days = array_values(array_filter($this->days, static fn (MeasurementDay $measured): bool => $measured->ownerId()->equals($userId)));
        usort($days, static fn (MeasurementDay $a, MeasurementDay $b): int => $a->day() <=> $b->day());

        return $days;
    }

    private static function key(UserId $userId, \DateTimeImmutable $day): string
    {
        return $userId->value.'|'.$day->format('Y-m-d');
    }
}
