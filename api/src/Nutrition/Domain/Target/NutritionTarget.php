<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Nutrition\Domain\Exception\InvalidJournalDay;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * Daily targets in force from a day on. Never edited: a change is a new target, so that every past
 * day is judged against the target of its time and "why did this value change?" has an answer
 * (spec §10). On a given day, the target in force is the last one *set* among those already
 * effective: a newer decision always wins over an older one, even one planned for later.
 */
class NutritionTarget extends AggregateRoot
{
    /**
     * A target starts today or tomorrow, never in the past. "Today" is the user's: one day of
     * slack before the server's today covers time zones behind it.
     */
    public const int EARLIEST_START_DAYS_AGO = 1;
    public const int LATEST_START_DAYS_AHEAD = 1;

    private function __construct(
        private readonly NutritionTargetId $id,
        private readonly UserId $ownerId,
        private readonly \DateTimeImmutable $effectiveFrom,
        private readonly MacroTargets $targets,
        private readonly TargetSource $source,
        private readonly ?EnergyEstimate $estimate,
        private readonly \DateTimeImmutable $setAt,
    ) {
    }

    public static function set(
        NutritionTargetId $id,
        UserId $owner,
        \DateTimeImmutable $effectiveFrom,
        MacroTargets $targets,
        TargetSource $source,
        ?EnergyEstimate $estimate,
        ?self $previous,
        \DateTimeImmutable $now,
    ): self {
        $start = $effectiveFrom->setTime(0, 0);
        $today = $now->setTime(0, 0);
        if ($start < $today->modify(\sprintf('-%d day', self::EARLIEST_START_DAYS_AGO)) || $start > $today->modify(\sprintf('+%d day', self::LATEST_START_DAYS_AHEAD))) {
            throw InvalidJournalDay::because('a new target starts today or tomorrow');
        }

        $target = new self($id, $owner, $start, $targets, $source, $estimate, $now);
        $target->recordThat(new NutritionTargetSet($id, $owner, $start, $previous?->targets, $targets, $source, $now));

        return $target;
    }

    public function id(): NutritionTargetId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function effectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function targets(): MacroTargets
    {
        return $this->targets;
    }

    public function source(): TargetSource
    {
        return $this->source;
    }

    public function estimate(): ?EnergyEstimate
    {
        return $this->estimate;
    }

    public function setAt(): \DateTimeImmutable
    {
        return $this->setAt;
    }
}
