<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

use App\Progression\Domain\Exception\InvalidWeightGoal;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\Weight;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * Where the user's weight is headed (spec §52): a final target and milestones on the way
 * (48 → 50 → 52… → 60 kg), from the weight they had when they set it. Gaining or losing follows
 * from that start. A new goal ends the current one; an ended goal is kept, never edited.
 * Independent of the Nutrition goal: the Coach compares them (phase 6).
 */
class WeightGoal extends AggregateRoot
{
    public const float MIN_CHANGE_KG = 0.5;
    public const int MAX_MILESTONES = 10;

    /**
     * @param list<float> $milestones in the order they come, target excluded
     */
    private function __construct(
        private readonly WeightGoalId $id,
        private readonly UserId $ownerId,
        private readonly \DateTimeImmutable $startDay,
        private readonly float $startKg,
        private readonly float $targetKg,
        private readonly array $milestones,
        private readonly \DateTimeImmutable $setAt,
        private ?\DateTimeImmutable $endedAt = null,
    ) {
    }

    /**
     * @param list<Weight> $milestones in any order: they are sorted the way the weight goes
     */
    public static function set(WeightGoalId $id, UserId $owner, DayWeight $start, Weight $target, array $milestones, \DateTimeImmutable $now): self
    {
        if (abs($target->kg - $start->kg) < self::MIN_CHANGE_KG) {
            throw InvalidWeightGoal::because(\sprintf('a goal is at least %.1f kg away from the current weight', self::MIN_CHANGE_KG));
        }
        if (\count($milestones) > self::MAX_MILESTONES) {
            throw InvalidWeightGoal::because(\sprintf('at most %d milestones', self::MAX_MILESTONES));
        }
        $gaining = $target->kg > $start->kg;
        $kgs = array_map(static fn (Weight $milestone): float => $milestone->kg, $milestones);
        $gaining ? sort($kgs) : rsort($kgs);
        foreach ($kgs as $i => $kg) {
            $between = $gaining ? $kg > $start->kg && $kg < $target->kg : $kg < $start->kg && $kg > $target->kg;
            if (!$between) {
                throw InvalidWeightGoal::because('each milestone lies between the current weight and the target');
            }
            if ($i > 0 && $kg === $kgs[$i - 1]) {
                throw InvalidWeightGoal::because('milestones are all different');
            }
        }

        return new self($id, $owner, $start->day, $start->kg, $target->kg, $kgs, $now);
    }

    public function end(\DateTimeImmutable $now): void
    {
        $this->endedAt ??= $now;
    }

    public function direction(): TrendDirection
    {
        return $this->targetKg > $this->startKg ? TrendDirection::Gaining : TrendDirection::Losing;
    }

    /**
     * @return list<float> milestones then the target: every weight to reach, in order
     */
    public function steps(): array
    {
        return [...$this->milestones, $this->targetKg];
    }

    /** Whether a weight is at or past a step, the way the goal goes. */
    public function reaches(float $kg, float $step): bool
    {
        return TrendDirection::Gaining === $this->direction() ? $kg >= $step : $kg <= $step;
    }

    public function id(): WeightGoalId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function startDay(): \DateTimeImmutable
    {
        return $this->startDay;
    }

    public function startKg(): float
    {
        return $this->startKg;
    }

    public function targetKg(): float
    {
        return $this->targetKg;
    }

    /**
     * @return list<float>
     */
    public function milestones(): array
    {
        return $this->milestones;
    }

    public function setAt(): \DateTimeImmutable
    {
        return $this->setAt;
    }

    public function endedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }
}
