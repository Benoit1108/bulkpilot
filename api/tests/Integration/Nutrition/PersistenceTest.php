<?php

declare(strict_types=1);

namespace App\Tests\Integration\Nutrition;

use App\Nutrition\Application\Builder\BuildMealHandler;
use App\Nutrition\Application\Catalogue\ImportCatalogue;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\EnergyInputs;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Nutrition\Domain\Target\Sex;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures/ciqual';

    private EntityManagerInterface $entityManager;
    private FoodRepository $foods;
    private UserId $alex;
    private UserId $sam;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->foods = self::getContainer()->get(FoodRepository::class);
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->sam = new UserId('00000000-0000-7000-8000-000000000511');
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testTheCatalogueImportIsIdempotent(): void
    {
        $bus = self::getContainer()->get(CommandBus::class);

        $bus->dispatch($first = new ImportCatalogue(self::FIXTURES));
        $bus->dispatch($second = new ImportCatalogue(self::FIXTURES));

        self::assertSame(12, $first->report->created);
        self::assertSame([], $first->report->skipped);
        self::assertSame(0, $second->report->created);
        self::assertSame(12, $second->report->updated);
        $catalogue = $this->foods->catalogue(FoodSource::Ciqual);
        self::assertCount(12, $catalogue);
        self::assertArrayHasKey('31067', $catalogue);
        self::assertCount(\count(BuildMealHandler::STAPLES), $this->foods->fromCatalogue(FoodSource::Ciqual, BuildMealHandler::STAPLES), 'every Meal Builder staple exists in CIQUAL');
        self::assertSame(300.0, $catalogue['31067']->facts()->energyKcal, 'molasses: 4 × 74.7 + 9 × 0.1, computed from its macros');
    }

    public function testSearchRanksOwnFoodsThenPrefixesAndHidesOthersFoods(): void
    {
        self::getContainer()->get(CommandBus::class)->dispatch(new ImportCatalogue(self::FIXTURES));
        $this->foods->save(Food::createCustom(new FoodId('00000000-0000-7000-8000-0000000000c1'), $this->alex, 'Galette de riz', 'Bjorg', FoodState::NotApplicable, new NutritionFacts(380, 8, 80, 3), []));
        $this->foods->save(Food::createCustom(new FoodId('00000000-0000-7000-8000-0000000000c2'), $this->sam, 'Riz au lait', null, FoodState::Cooked, new NutritionFacts(120, 3, 20, 3), []));
        $this->entityManager->flush();

        $names = array_map(static fn (Food $food): string => $food->name(), $this->foods->search($this->alex, 'riz', 10));

        self::assertSame(['Galette de riz', 'Riz blanc, cru', 'Riz blanc, cuit, non salé'], $names);
        self::assertSame(['Poulet, filet, sans peau, sauté/poêlé'], array_map(static fn (Food $food): string => $food->name(), $this->foods->search($this->alex, 'poulet saute', 10)));
        self::assertSame([], $this->foods->search($this->alex, '100%_', 10), 'LIKE wildcards are literal');
        self::assertSame([], $this->foods->search($this->alex, 'a b c', 10), 'one-letter words are not searched');
        self::assertCount(3, $this->foods->search($this->alex, 'riz x', 10), '… and do not filter out the others');
        $galette = $this->foods->getVisibleTo(new FoodId('00000000-0000-7000-8000-0000000000c1'), $this->alex);
        $galette->delete($this->now);
        $this->entityManager->flush();
        self::assertNotContains('Galette de riz', array_map(static fn (Food $food): string => $food->name(), $this->foods->search($this->alex, 'riz', 10)), 'deleted foods are not found');
        self::assertCount(1, $this->foods->search($this->alex, 'riz', 1));
    }

    public function testAFoodRoundTripsWithItsPortionsAndIsSoftDeleted(): void
    {
        $id = new FoodId('00000000-0000-7000-8000-0000000000c1');
        $food = Food::createCustom($id, $this->alex, 'Pâtes', 'Barilla', FoodState::Cooked, new NutritionFacts(157, 5.8, 30.6, 0.9, 1.8, 0.5, 0.2, 0.01), [new Portion('1 assiette', 250)]);
        $this->foods->save($food);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->foods->getVisibleTo($id, $this->alex);
        self::assertSame([['1 assiette', 250.0]], array_map(static fn (Portion $portion): array => [$portion->label, $portion->grams], $loaded->portions()));
        self::assertSame(get_object_vars(new NutritionFacts(157, 5.8, 30.6, 0.9, 1.8, 0.5, 0.2, 0.01)), get_object_vars($loaded->facts()));
        self::assertSame([$loaded], $this->foods->customFoodsOf($this->alex));
        $this->foods->save(Food::createCustom(new FoodId('00000000-0000-7000-8000-0000000000c3'), $this->alex, 'Abricots secs', null, FoodState::NotApplicable, new NutritionFacts(270, 3, 60, 0.5), []));
        $this->entityManager->flush();
        self::assertSame(['Abricots secs', 'Pâtes'], array_map(static fn (Food $food): string => $food->name(), $this->foods->customFoodsOf($this->alex)), 'by name');
        self::assertSame([], $this->foods->visibleAmong($this->sam, [$id]));

        $loaded->delete($this->now);
        $this->entityManager->flush();
        self::assertSame(['Abricots secs'], array_map(static fn (Food $food): string => $food->name(), $this->foods->customFoodsOf($this->alex)));
    }

    public function testJournalQueriesAreScopedToTheOwner(): void
    {
        $rice = Food::fromCatalogue(new FoodId('00000000-0000-7000-8000-0000000000c1'), FoodSource::Ciqual, '9100', 'Riz blanc, cru', null, new NutritionFacts(356, 7.4, 78.6, 0.7, 1.4));
        $oil = Food::fromCatalogue(new FoodId('00000000-0000-7000-8000-0000000000c2'), FoodSource::Ciqual, '17440', 'Huile', null, new NutritionFacts(901, 0, 0, 100));
        $this->foods->save($rice);
        $this->foods->save($oil);
        $entries = self::getContainer()->get(JournalEntryRepository::class);
        $log = function (UserId $owner, Food $food, string $day, float $grams, string $at) use ($entries): JournalEntry {
            $entry = JournalEntry::log(new JournalEntryId(\sprintf('00000000-0000-7000-8000-%012d', \count($this->entityManager->getUnitOfWork()->getIdentityMap()[JournalEntry::class] ?? []) + 1)), $owner, new \DateTimeImmutable($day), MealSlot::Lunch, LoggedFood::snapshotOf($food), $grams, new \DateTimeImmutable($at));
            $entries->save($entry);
            $this->entityManager->flush();

            return $entry;
        };
        $first = $log($this->alex, $rice, '2026-09-27', 100, '2026-09-27 12:00');
        $log($this->alex, $oil, '2026-09-27', 10, '2026-09-27 12:05');
        $log($this->alex, $rice, '2026-09-28', 200, '2026-09-28 12:00');
        $log($this->sam, $oil, '2026-09-28', 50, '2026-09-28 13:00');
        $this->entityManager->clear();

        self::assertCount(2, $entries->ofDay($this->alex, new \DateTimeImmutable('2026-09-27')));
        self::assertSame($first->id()->value, $entries->ofDay($this->alex, new \DateTimeImmutable('2026-09-27'))[0]->id()->value);
        $totals = $entries->dailyTotals($this->alex, new \DateTimeImmutable('2026-09-22'), new \DateTimeImmutable('2026-09-28'));
        self::assertSame(['2026-09-27', '2026-09-28'], array_keys($totals));
        self::assertEqualsWithDelta(356 + 90.1, $totals['2026-09-27']->amounts->energyKcal, 0.001);
        self::assertEqualsWithDelta(1.4, $totals['2026-09-27']->amounts->fibre, 0.001, 'unknown fibre counts as nothing');
        self::assertEqualsWithDelta(712.0, $totals['2026-09-28']->amounts->energyKcal, 0.001);
        self::assertSame(['9100', '17440'], array_map(fn (FoodId $id): string => (string) $this->foods->getVisibleTo($id, $this->alex)->sourceReference(), $entries->recentFoods($this->alex, 10)));
        self::assertCount(1, $entries->recentFoods($this->sam, 10));
    }

    public function testTargetsKeepTheirHistoryAndEstimate(): void
    {
        $targets = self::getContainer()->get(NutritionTargetRepository::class);
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Male, 30, 180, 80, Lifestyle::Active, 4, Goal::Bulk, 0.25));
        $targets->save(NutritionTarget::set(new NutritionTargetId('00000000-0000-7000-8000-000000000071'), $this->alex, new \DateTimeImmutable('2026-09-01'), new MacroTargets(2800, 150, 360, 80), TargetSource::Manual, null, null, new \DateTimeImmutable('2026-09-01 08:00')));
        $targets->save(NutritionTarget::set(new NutritionTargetId('00000000-0000-7000-8000-000000000072'), $this->alex, new \DateTimeImmutable('2026-09-20'), $estimate->targets, TargetSource::Estimate, $estimate, null, new \DateTimeImmutable('2026-09-20 08:00')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(2800, $targets->inForceOn($this->alex, new \DateTimeImmutable('2026-09-19'))?->targets()->energyKcal);
        $current = $targets->inForceOn($this->alex, new \DateTimeImmutable('2026-09-28'));
        self::assertSame(serialize($estimate), serialize($current?->estimate()), 'the estimate snapshot round-trips');
        self::assertNull($targets->inForceOn($this->sam, new \DateTimeImmutable('2026-09-28')));
        self::assertCount(2, $targets->history($this->alex, 10));
        self::assertCount(1, $targets->history($this->alex, 1));
        self::assertSame(2800, $targets->getOwnedBy(new NutritionTargetId('00000000-0000-7000-8000-000000000071'), $this->alex)->targets()->energyKcal);
    }

    public function testTheLastTargetSetWinsAndTiesAreSettledByCreationOrder(): void
    {
        $targets = self::getContainer()->get(NutritionTargetRepository::class);
        $at = new \DateTimeImmutable('2026-09-28 10:00:00');
        // Planned for tomorrow, then replaced from today, both within the same second.
        $targets->save(NutritionTarget::set(new NutritionTargetId('01920000-0000-7000-8000-000000000001'), $this->alex, $at->modify('+1 day'), new MacroTargets(3000, 150, 400, 88), TargetSource::Manual, null, null, $at));
        $targets->save(NutritionTarget::set(new NutritionTargetId('01920000-0000-7000-8000-000000000002'), $this->alex, $at, new MacroTargets(2800, 150, 360, 80), TargetSource::Manual, null, null, $at));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(2800, $targets->inForceOn($this->alex, $at->modify('+1 day'))?->targets()->energyKcal);
        self::assertSame([2800, 3000], array_map(static fn (NutritionTarget $target): int => $target->targets()->energyKcal, $targets->history($this->alex, 10)));
    }

    public function testAStoredEstimateStaysReadableWhenTheRulesTighten(): void
    {
        $targets = self::getContainer()->get(NutritionTargetRepository::class);
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Male, 30, 180, 80, Lifestyle::Active, 4, Goal::Bulk, 0.25));
        $id = new NutritionTargetId('00000000-0000-7000-8000-000000000073');
        $targets->save(NutritionTarget::set($id, $this->alex, $this->now, $estimate->targets, TargetSource::Estimate, $estimate, null, $this->now));
        $this->entityManager->flush();
        // As if the minimum age had been raised after this estimate was saved.
        $this->entityManager->getConnection()->executeStatement("UPDATE nutrition_targets SET estimate = jsonb_set(estimate::jsonb, '{inputs,age}', '12')::json WHERE id = :id", ['id' => $id->value]);
        $this->entityManager->clear();

        self::assertSame(12, $targets->getOwnedBy($id, $this->alex)->estimate()?->inputs->age);
    }

    public function testTheMealSplitIsStoredPerUser(): void
    {
        $splits = self::getContainer()->get(MealSplitRepository::class);
        $splits->save(MealSplit::of($this->alex, ['breakfast' => 20, 'lunch' => 40, 'dinner' => 40]));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(40, $splits->find($this->alex)?->shareOf(MealSlot::Dinner));
        self::assertNull($splits->find($this->sam));
    }
}
