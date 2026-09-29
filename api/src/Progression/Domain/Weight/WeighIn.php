<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

use App\Progression\Domain\DayWindow;
use App\Progression\Domain\Exception\InvalidWeighIn;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * The weight of one of the user's days (spec §51): one per day, weighing again that day
 * replaces it. Days are the user's (their calendar, sent by the device), any day of the past
 * can be filled in afterwards.
 */
class WeighIn extends AggregateRoot
{
    private function __construct(
        private readonly WeighInId $id,
        private readonly UserId $ownerId,
        private readonly \DateTimeImmutable $day,
        private Weight $weight,
        private readonly \DateTimeImmutable $recordedAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function record(WeighInId $id, UserId $owner, \DateTimeImmutable $day, Weight $weight, \DateTimeImmutable $now): self
    {
        $day = $day->setTime(0, 0);
        if (!DayWindow::allows($day, $now)) {
            throw InvalidWeighIn::because('a weigh-in is for a day that has come');
        }

        $weighIn = new self($id, $owner, $day, $weight, $now, $now);
        $weighIn->recordThat(new WeighInRecorded($id, $owner, $day, $weight, $now));

        return $weighIn;
    }

    /** Weighing again the same day: the new weight replaces the old one. */
    public function correct(Weight $weight, \DateTimeImmutable $now): void
    {
        if ($weight->equals($this->weight)) {
            return;
        }
        $this->weight = $weight;
        $this->updatedAt = $now;
        $this->recordThat(new WeighInRecorded($this->id, $this->ownerId, $this->day, $weight, $now));
    }

    public function id(): WeighInId
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

    public function weight(): Weight
    {
        return $this->weight;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
