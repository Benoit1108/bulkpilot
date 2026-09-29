<?php

declare(strict_types=1);

namespace App\Coach\Domain\Report;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\Pace;
use App\Shared\Domain\Identifier\UserId;

/**
 * The report of a finished week (spec §59): weight, eating, training, the Coach's verdict and the
 * action it asks for. Drawn once, when the Coach first looks after the week ended, then kept.
 *
 * @phpstan-import-type WeekFiguresArray from WeekFigures
 * @phpstan-import-type AdherenceArray from Adherence
 */
class WeeklyReport
{
    /**
     * @param WeekFiguresArray $figures
     * @param AdherenceArray   $adherence
     */
    private function __construct(
        private readonly WeeklyReportId $id,
        private readonly UserId $ownerId,
        private readonly \DateTimeImmutable $weekStart,
        private readonly array $figures,
        private readonly array $adherence,
        private readonly Pace $verdict,
        private readonly ?string $action,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param ?string $action what the Coach asks for most, in the imperative; null to carry on with the plan
     */
    public static function draw(WeeklyReportId $id, UserId $owner, \DateTimeImmutable $weekStart, WeekFacts $week, ?string $action, \DateTimeImmutable $now): self
    {
        return new self(
            $id,
            $owner,
            $weekStart->setTime(0, 0),
            $week->figures->toArray(),
            $week->adherence->toArray(),
            Pace::of($week->figures->paceKgPerWeek, $week->figures->kgPerWeek),
            $action,
            $now,
        );
    }

    public function id(): WeeklyReportId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function weekStart(): \DateTimeImmutable
    {
        return $this->weekStart;
    }

    public function figures(): WeekFigures
    {
        return WeekFigures::fromArray($this->figures);
    }

    public function adherence(): Adherence
    {
        return Adherence::fromArray($this->adherence);
    }

    public function verdict(): Pace
    {
        return $this->verdict;
    }

    public function action(): ?string
    {
        return $this->action;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
