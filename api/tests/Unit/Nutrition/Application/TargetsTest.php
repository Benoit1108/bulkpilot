<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Audit\LogTargetChange;
use App\Nutrition\Application\Target\ChangeMealSplit;
use App\Nutrition\Application\Target\ChangeMealSplitHandler;
use App\Nutrition\Application\Target\EstimateRequest;
use App\Nutrition\Application\Target\EstimateTargets;
use App\Nutrition\Application\Target\EstimateTargetsHandler;
use App\Nutrition\Application\Target\GetMealSplit;
use App\Nutrition\Application\Target\GetMealSplitHandler;
use App\Nutrition\Application\Target\GetTarget;
use App\Nutrition\Application\Target\GetTargetHandler;
use App\Nutrition\Application\Target\ListTargetHistory;
use App\Nutrition\Application\Target\ListTargetHistoryHandler;
use App\Nutrition\Application\Target\SetTargets;
use App\Nutrition\Application\Target\SetTargetsHandler;
use App\Nutrition\Application\Target\TargetView;
use App\Nutrition\Domain\Exception\InvalidEnergyInputs;
use App\Nutrition\Domain\Exception\InvalidJournalDay;
use App\Nutrition\Domain\Exception\InvalidMealSplit;
use App\Nutrition\Domain\Exception\NutritionTargetNotFound;
use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetSet;
use App\Nutrition\Domain\Target\Sex;
use App\Nutrition\Domain\Target\TargetSource;
use App\Tests\Support\Audit\SpyAuditTrail;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class TargetsTest extends NutritionTestCase
{
    public function testTheEstimateDerivesTheAgeFromTheBirthDate(): void
    {
        $estimate = new EstimateTargetsHandler(new EnergyCalculator(), $this->clock)(new EstimateTargets($this->request('1996-09-29')));

        self::assertSame(29, $estimate->inputs->age, 'the 30th birthday is tomorrow');
        self::assertSame(3310, $estimate->targets->energyKcal, 'one year younger: 5 kcal more BMR × 1.7');
    }

    public function testABirthDateInTheFutureIsRefused(): void
    {
        $this->expectException(InvalidEnergyInputs::class);

        $this->request('2027-01-01')->inputs($this->clock->now());
    }

    public function testAnAcceptedEstimateIsRecordedAsSuchWithItsCalculation(): void
    {
        $this->set('2026-09-28', new MacroTargets(3300, 144, 474, 92), $this->request());

        $target = $this->history()[0];
        self::assertSame(TargetSource::Estimate, $target->source);
        self::assertSame(1780.0, $target->estimate?->basalMetabolicRate);
        $event = $this->events->published[0];
        self::assertInstanceOf(NutritionTargetSet::class, $event);
        self::assertNull($event->previous?->energyKcal);
    }

    public function testAnAdjustedEstimateIsManualButKeepsItsStartingPoint(): void
    {
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80), null);
        $this->set('2026-09-29', new MacroTargets(3200, 150, 450, 90), $this->request());

        $history = $this->history();
        self::assertSame([3200, 2800], array_map(static fn ($view): int => $view->targets->energyKcal, $history));
        self::assertSame(TargetSource::Manual, $history[0]->source);
        self::assertSame(3300, $history[0]->estimate?->targets->energyKcal);
        $event = $this->events->published[1];
        self::assertInstanceOf(NutritionTargetSet::class, $event);
        self::assertSame(2800, $event->previous?->energyKcal);
    }

    public function testTheLastDecisionWinsEvenOverOnePlannedForLater(): void
    {
        $this->set('2026-09-29', new MacroTargets(3000, 150, 400, 88), null); // planned for tomorrow…
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80), null); // …then changed, from today

        self::assertSame(2800, $this->targets->inForceOn($this->alex, $this->day('2026-09-29'))?->targets()->energyKcal);
    }

    public function testTargetsSetWithinTheSameSecondAreOrderedByCreation(): void
    {
        $handler = new SetTargetsHandler($this->targets, new EnergyCalculator(), $this->events, $this->clock);
        foreach ([2500, 2600, 2700] as $kcal) {
            $handler(new SetTargets(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day(), new MacroTargets($kcal, 150, intdiv($kcal - 1200, 4), 67), null));
        }

        self::assertSame([2700, 2600, 2500], array_map(static fn ($view): int => $view->targets->energyKcal, $this->history()));
        self::assertSame(2700, new GetTargetHandler($this->targets)(new GetTarget($this->alex, new NutritionTargetId($this->history()[0]->id)))->targets->energyKcal);
    }

    public function testSomeoneElsesTargetIsNotFound(): void
    {
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80), null);

        $this->expectException(NutritionTargetNotFound::class);
        new GetTargetHandler($this->targets)(new GetTarget($this->sam, new NutritionTargetId($this->history()[0]->id)));
    }

    public function testATargetCannotRewriteThePast(): void
    {
        $this->expectException(InvalidJournalDay::class);

        $this->set('2026-09-20', new MacroTargets(2800, 150, 360, 80), null);
    }

    public function testEveryTargetChangeIsAuditedWithWhereItCameFrom(): void
    {
        $audit = new SpyAuditTrail();
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80), null);
        $event = $this->events->published[0];
        self::assertInstanceOf(NutritionTargetSet::class, $event);

        new LogTargetChange($audit)($event);

        self::assertSame([[
            'user' => self::ALEX,
            'action' => 'nutrition.target_set',
            'details' => [
                'previous_kcal' => null,
                'kcal' => 2800,
                'effective_from' => '2026-09-28',
                'origin' => 'manual',
            ],
            'source' => 'user',
            'at' => '2026-09-28T10:00:01+00:00',
        ]], $audit->recorded);
    }

    public function testTheMealSplitDefaultsThenFollowsTheUser(): void
    {
        $get = new GetMealSplitHandler($this->mealSplits);
        $change = new ChangeMealSplitHandler($this->mealSplits);
        self::assertSame(15, $get(new GetMealSplit($this->alex))['gainer']);

        $change(new ChangeMealSplit($this->alex, ['breakfast' => 20, 'lunch' => 40, 'dinner' => 40]));
        $change(new ChangeMealSplit($this->alex, ['breakfast' => 20, 'lunch' => 30, 'gainer' => 20, 'dinner' => 30]));

        self::assertSame(['breakfast' => 20, 'lunch' => 30, 'snack' => 0, 'gainer' => 20, 'dinner' => 30], $get(new GetMealSplit($this->alex)));
        self::assertSame(15, $get(new GetMealSplit($this->sam))['gainer']);

        $this->expectException(InvalidMealSplit::class);
        $change(new ChangeMealSplit($this->alex, ['breakfast' => 50]));
    }

    private function request(string $birthDate = '1996-01-15'): EstimateRequest
    {
        return new EstimateRequest(Sex::Male, new \DateTimeImmutable($birthDate), 180, 80, Lifestyle::Active, 4, Goal::Bulk, 0.25);
    }

    private function set(string $from, MacroTargets $targets, ?EstimateRequest $estimate): void
    {
        $this->clock->modify('+1 second');
        new SetTargetsHandler($this->targets, new EnergyCalculator(), $this->events, $this->clock)(
            new SetTargets(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day($from), $targets, $estimate),
        );
    }

    /**
     * @return list<TargetView>
     */
    private function history(): array
    {
        return new ListTargetHistoryHandler($this->targets)(new ListTargetHistory($this->alex));
    }
}
