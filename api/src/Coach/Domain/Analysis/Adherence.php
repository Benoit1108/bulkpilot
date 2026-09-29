<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

use App\Coach\Domain\Rule\WeighInRule;

/**
 * How well the plan was kept over a week (spec §58): the mean of its parts, each one explained by
 * what was done of what was expected. A part without anything to expect (no target, no program)
 * is left out rather than counted as missed; a week without anything recorded at all (no day
 * logged, no weigh-in, no session) has no score rather than a zero.
 *
 * @phpstan-type AdherenceArray array{recorded: bool, parts: list<array{part: string, done: int, expected: int}>}
 */
final readonly class Adherence
{
    /**
     * @param list<AdherenceComponent> $components
     */
    private function __construct(public array $components, public bool $recorded)
    {
    }

    public static function of(?TargetFacts $target, IntakeFacts $week, int $weighIns, TrainingFacts $training): self
    {
        $components = [];
        if (null !== $target) {
            $components[] = new AdherenceComponent(AdherencePart::Calories, $week->daysOnKcal($target->energyKcal), $week->windowDays);
            if ($target->proteinGrams > 0) {
                $components[] = new AdherenceComponent(AdherencePart::Protein, $week->daysOnProtein($target->proteinGrams), $week->windowDays);
            }
        }
        if (null !== $training->plannedPerWeek && $training->plannedPerWeek > 0) {
            $components[] = new AdherenceComponent(AdherencePart::Training, $training->sessions, $training->plannedPerWeek);
        }
        $components[] = new AdherenceComponent(AdherencePart::WeighIns, $weighIns, WeighInRule::MIN_PER_WEEK);

        return new self($components, $week->logged() > 0 || $weighIns > 0 || $training->sessions > 0);
    }

    /** 0 to 100; null when nothing was recorded that week. */
    public function score(): ?int
    {
        if (!$this->recorded) {
            return null;
        }

        return (int) round(array_sum(array_map(static fn (AdherenceComponent $component): int => $component->score(), $this->components)) / \count($this->components));
    }

    /**
     * @return AdherenceArray
     */
    public function toArray(): array
    {
        return [
            'recorded' => $this->recorded,
            'parts' => array_map(static fn (AdherenceComponent $component): array => ['part' => $component->part->value, 'done' => $component->done, 'expected' => $component->expected], $this->components),
        ];
    }

    /**
     * @param AdherenceArray $adherence
     */
    public static function fromArray(array $adherence): self
    {
        return new self(
            array_map(static fn (array $part): AdherenceComponent => new AdherenceComponent(AdherencePart::from($part['part']), $part['done'], $part['expected']), $adherence['parts']),
            $adherence['recorded'],
        );
    }
}
