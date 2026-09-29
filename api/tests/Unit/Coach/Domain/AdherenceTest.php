<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Domain;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\AdherenceComponent;
use App\Coach\Domain\Analysis\AdherencePart;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Tests\Support\Coach\CoachTestCase;

final class AdherenceTest extends CoachTestCase
{
    /**
     * @param list<AdherenceComponent> $components
     *
     * @return list<array{string, int, int}>
     */
    public static function parts(array $components): array
    {
        return array_map(static fn (AdherenceComponent $part): array => [$part->part->value, $part->done, $part->expected], $components);
    }

    public function testADayIsOnTargetWithinTenPercentAndOnProteinFromNinetyPercent(): void
    {
        $week = new IntakeFacts(7, [
            ['kcal' => 1980.0, 'protein' => 108.0],
            ['kcal' => 2420.0, 'protein' => 107.9],
            ['kcal' => 1979.0, 'protein' => 150.0],
            ['kcal' => 2421.0, 'protein' => 0.0],
        ]);

        self::assertSame(2, $week->daysOnKcal(2200));
        self::assertSame(2, $week->daysOnProtein(120));
        self::assertSame(4, $week->logged());
        self::assertSame(2200.0, $week->averageKcal());
        self::assertSame(91.0, $week->averageProtein());
        self::assertNull(new IntakeFacts(7, [])->averageKcal());
    }

    public function testEachPartIsExplainedAndTheScoreIsTheirMean(): void
    {
        $target = new TargetFacts(new \DateTimeImmutable('2026-09-01'), 2200, 120);
        $week = new IntakeFacts(7, self::eaten(7, 2200, 50));

        $adherence = Adherence::of($target, $week, 3, new TrainingFacts(2, 3, 0, []));

        self::assertSame([['calories', 7, 7], ['protein', 0, 7], ['training', 2, 3], ['weigh_ins', 3, 3]], self::parts($adherence->components));
        self::assertSame([100, 0, 67, 100], array_map(static fn (AdherenceComponent $part): int => $part->score(), $adherence->components));
        // (100 + 0 + 66.7 + 100) / 4, from the rounded parts.
        self::assertSame(67, $adherence->score());
    }

    public function testMoreThanExpectedCountsAsAllOfIt(): void
    {
        self::assertSame(100, new AdherenceComponent(AdherencePart::Training, 5, 3)->score());
        self::assertSame(100, new AdherenceComponent(AdherencePart::WeighIns, 7, 3)->score());
    }

    public function testPartsWithNothingExpectedAreLeftOut(): void
    {
        $adherence = Adherence::of(null, new IntakeFacts(7, []), 1, new TrainingFacts(4, null, 0, []));

        self::assertSame([['weigh_ins', 1, 3]], self::parts($adherence->components));
        self::assertSame(33, $adherence->score());

        $noProtein = Adherence::of(new TargetFacts(new \DateTimeImmutable('2026-09-01'), 2200, 0), new IntakeFacts(7, []), 3, new TrainingFacts(0, 0, 0, []));
        self::assertSame([AdherencePart::Calories, AdherencePart::WeighIns], array_map(static fn (AdherenceComponent $part): AdherencePart => $part->part, $noProtein->components));
    }

    public function testAWeekWithNothingRecordedHasNoScore(): void
    {
        $target = new TargetFacts(new \DateTimeImmutable('2026-09-01'), 2200, 120);

        $nothing = Adherence::of($target, new IntakeFacts(7, []), 0, new TrainingFacts(0, 3, 0, []));
        $oneSession = Adherence::of($target, new IntakeFacts(7, []), 0, new TrainingFacts(1, 3, 0, []));

        self::assertNull($nothing->score());
        self::assertSame(8, $oneSession->score(), '(0 + 0 + 33 + 0) / 4: something was recorded');
    }

    public function testItSurvivesItsStorage(): void
    {
        $adherence = Adherence::of(new TargetFacts(new \DateTimeImmutable('2026-09-01'), 2200, 120), new IntakeFacts(7, self::eaten(5, 2200)), 4, new TrainingFacts(2, 3, 0, []));

        $back = Adherence::fromArray($adherence->toArray());

        self::assertSame(self::parts($adherence->components), self::parts($back->components));
        self::assertSame($adherence->score(), $back->score());
        self::assertNull(Adherence::fromArray(Adherence::of(null, new IntakeFacts(7, []), 0, new TrainingFacts(0, null, 0, []))->toArray())->score());
    }
}
