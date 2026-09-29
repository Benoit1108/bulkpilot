<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

use App\Progression\Domain\DayWindow;
use App\Progression\Domain\Exception\InvalidMeasurement;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * The measurements taken one day (spec §54): some or all of the measures, in centimetres. One
 * set a day: measuring again that day replaces it.
 */
class MeasurementDay extends AggregateRoot
{
    /**
     * @param array<string, float> $values centimetres by measure key (a body part, or a custom measure's id)
     */
    private function __construct(
        private readonly MeasurementDayId $id,
        private readonly UserId $ownerId,
        private readonly \DateTimeImmutable $day,
        private array $values,
        private readonly \DateTimeImmutable $recordedAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, Length> $values the keys already checked against the user's measures
     */
    public static function record(MeasurementDayId $id, UserId $owner, \DateTimeImmutable $day, array $values, \DateTimeImmutable $now): self
    {
        if (!DayWindow::allows($day, $now)) {
            throw InvalidMeasurement::because('measurements are for a day that has come');
        }

        return new self($id, $owner, $day->setTime(0, 0), self::centimetres($values), $now, $now);
    }

    /**
     * @param array<string, Length> $values
     */
    public function replace(array $values, \DateTimeImmutable $now): void
    {
        $this->values = self::centimetres($values);
        $this->updatedAt = $now;
    }

    public function id(): MeasurementDayId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function day(): \DateTimeImmutable
    {
        return $this->day;
    }

    /**
     * @return array<string, float>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function valueOf(string $key): ?float
    {
        return $this->values[$key] ?? null;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<string, Length> $values
     *
     * @return array<string, float>
     */
    private static function centimetres(array $values): array
    {
        if ([] === $values) {
            throw InvalidMeasurement::because('a day of measurements has at least one');
        }

        return array_map(static fn (Length $length): float => $length->cm, $values);
    }
}
