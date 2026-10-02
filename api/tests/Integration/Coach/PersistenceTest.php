<?php

declare(strict_types=1);

namespace App\Tests\Integration\Coach;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\Pace;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Coach\Domain\Recommendation\CoachMessage;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Coach\Domain\Recommendation\Status;
use App\Coach\Domain\Report\WeekFacts;
use App\Coach\Domain\Report\WeekFigures;
use App\Coach\Domain\Report\WeeklyReport;
use App\Coach\Domain\Report\WeeklyReportId;
use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Coach\Domain\Settings\Personality;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private const string ALEX = '0199a0b1-7c3e-7d4a-9b2f-00000000c001';
    private const string SAM = '0199a0b1-7c3e-7d4a-9b2f-00000000c002';

    private EntityManagerInterface $entityManager;
    private RecommendationRepository $recommendations;
    private \DateTimeImmutable $now;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->recommendations = self::getContainer()->get(RecommendationRepository::class);
        $this->now = new \DateTimeImmutable('2026-09-29 08:00:00');
    }

    private function make(Kind $kind, string $owner = self::ALEX, ?\DateTimeImmutable $at = null, ?CalorieProposal $proposal = null): Recommendation
    {
        $recommendation = Recommendation::make(
            new RecommendationId(\sprintf('0199a0b1-7c3e-7d4a-9b2f-%012d', ++$this->ids)),
            new UserId($owner),
            new Finding($kind, Level::Watch, ['exercises' => [['exercise' => 'Squat', 'sessions' => 5, 'since' => '2026-08-01']], 'percentOfTarget' => 94], $proposal),
            new CoachMessage('Titre', 'Corps', ['Une', 'Deux'], 'Action', 'fake'),
            $at ?? $this->now,
        );
        $this->recommendations->save($recommendation);

        return $recommendation;
    }

    /**
     * @param list<Recommendation> $recommendations
     *
     * @return list<string>
     */
    private static function ids(array $recommendations): array
    {
        return array_map(static fn (Recommendation $recommendation): string => $recommendation->id()->value, $recommendations);
    }

    public function testARecommendationRoundTripsWithItsFiguresMessageAndProposal(): void
    {
        $made = $this->make(Kind::IncreaseCalories, proposal: new CalorieProposal(2200, 150));
        $this->flushAndClear();

        $loaded = $this->recommendations->getOwnedBy($made->id(), new UserId(self::ALEX));

        self::assertNotSame($made, $loaded);
        self::assertSame([Kind::IncreaseCalories, Level::Watch, 2350], [$loaded->kind(), $loaded->level(), $loaded->proposal()?->toKcal()]);
        self::assertSame($made->figures(), $loaded->figures());
        self::assertSame(['Titre', 'Corps', ['Une', 'Deux'], 'Action', 'fake'], [$loaded->message()->title, $loaded->message()->body, $loaded->message()->why, $loaded->message()->action, $loaded->message()->provider]);
        self::assertSame(Status::Pending, $loaded->status($this->now));
        self::assertSame([], $loaded->pullDomainEvents(), 'events are not persisted');
    }

    public function testOpenOnesAreTheUsersPendingOrPostponedTheLatestFirstThenById(): void
    {
        $older = $this->make(Kind::Records, at: $this->now->modify('-1 day'));
        $first = $this->make(Kind::LowProtein);
        $second = $this->make(Kind::Stall);
        $this->make(Kind::FewWeighIns)->reject($this->now);
        $this->make(Kind::MissedSessions)->expire($this->now);
        $this->make(Kind::LowProtein, self::SAM);
        $postponed = $this->make(Kind::IncompleteJournal, at: $this->now->modify('-2 days'));
        $postponed->postpone($this->now);
        $this->flushAndClear();

        self::assertSame(self::ids([$second, $first, $older, $postponed]), self::ids($this->recommendations->openOf(new UserId(self::ALEX))));
    }

    public function testDecidedOnesSinceATimeAreAcceptedOrRejected(): void
    {
        $this->make(Kind::Records)->accept($this->now);
        $this->make(Kind::FewWeighIns)->reject($this->now->modify('-8 days'));
        $this->make(Kind::MissedSessions)->expire($this->now);
        $this->make(Kind::LowProtein)->postpone($this->now);
        $this->flushAndClear();

        $kinds = array_map(static fn (Recommendation $one): Kind => $one->kind(), $this->recommendations->decidedSince(new UserId(self::ALEX), $this->now->modify('-7 days')));

        self::assertSame([Kind::Records], $kinds, 'from that time on, expired and postponed ones aside');
    }

    public function testTheHistoryPagesByTimeThenIdWithoutSkippingThoseOfTheSameAnalysis(): void
    {
        $older = $this->make(Kind::Records, at: $this->now->modify('-1 day'));
        $a = $this->make(Kind::LowProtein);
        $b = $this->make(Kind::Stall);
        $c = $this->make(Kind::FewWeighIns);
        $this->make(Kind::LowProtein, self::SAM);
        $this->flushAndClear();
        $alex = new UserId(self::ALEX);

        $first = $this->recommendations->historyOf($alex, 2);
        $next = $this->recommendations->historyOf($alex, 2, $first[1]->createdAt(), $first[1]->id()->value);

        self::assertSame(self::ids([$c, $b]), self::ids($first));
        self::assertSame(self::ids([$a, $older]), self::ids($next));
        self::assertSame(self::ids([$older]), self::ids($this->recommendations->historyOf($alex, 5, $this->now)), 'a time alone: strictly before it');
    }

    public function testTwoDecisionsAtOnceDoNotBothWin(): void
    {
        $made = $this->make(Kind::LowProtein);
        $this->flushAndClear();
        $loaded = $this->recommendations->getOwnedBy($made->id(), new UserId(self::ALEX));
        // Another request decided meanwhile.
        $this->entityManager->getConnection()->executeStatement('UPDATE coach_recommendations SET version = version + 1 WHERE id = ?', [$made->id()->value]);

        $loaded->accept($this->now);

        $this->expectException(OptimisticLockException::class);
        $this->entityManager->flush();
    }

    public function testSettingsRoundTripWithTheLastAdherenceAndTwoAnalysesAtOnceDoNotBothRun(): void
    {
        $settingsRepository = self::getContainer()->get(CoachSettingsRepository::class);
        $alex = new UserId(self::ALEX);
        self::assertSame(Personality::Direct, $settingsRepository->ofOrDefault($alex, $this->now)->personality(), 'the defaults before any');
        $settings = CoachSettings::defaults($alex, $this->now);
        $settings->change(Personality::Encouraging, -0.25, $this->now);
        $adherence = Adherence::of(new TargetFacts($this->now, 2200, 120), new IntakeFacts(7, [['kcal' => 2200.0, 'protein' => 130.0]]), 2, new TrainingFacts(1, 3, 0, []));
        $settings->markAnalysed($this->now, $adherence);
        $settingsRepository->save($settings);
        $this->flushAndClear();

        $loaded = $settingsRepository->ofOrDefault($alex, $this->now);
        self::assertSame([Personality::Encouraging, -0.25, '2026-09-29'], [$loaded->personality(), $loaded->paceKgPerWeek(), $loaded->lastAnalysedOn()?->format('Y-m-d')]);
        self::assertSame($adherence->toArray(), $loaded->adherence()?->toArray());

        $this->entityManager->getConnection()->executeStatement('UPDATE coach_settings SET version = version + 1 WHERE owner_id = ?', [self::ALEX]);
        $loaded->markAnalysed($this->now->modify('+1 day'), $adherence);
        $this->expectException(OptimisticLockException::class);
        $this->entityManager->flush();
    }

    private function report(string $owner, string $monday): WeeklyReport
    {
        $figures = new WeekFigures(59.6, 60.0, 0.1, 0.5, 2310.0, 98.0, 6, 2350, 120, 3, 3, 4);
        $adherence = Adherence::of(new TargetFacts($this->now, 2350, 120), new IntakeFacts(7, [['kcal' => 2350.0, 'protein' => 120.0]]), 4, new TrainingFacts(3, 3, 4, []));

        return WeeklyReport::draw(new WeeklyReportId(\sprintf('0199a0b1-7c3e-7d4a-9b2f-%012d', ++$this->ids)), new UserId($owner), new \DateTimeImmutable($monday), new WeekFacts($figures, $adherence), 'Continue ton programme.', $this->now);
    }

    public function testReportsRoundTripTheLatestWeekFirst(): void
    {
        $reports = self::getContainer()->get(WeeklyReportRepository::class);
        $reports->save($this->report(self::ALEX, '2026-09-14'));
        $reports->save($this->report(self::ALEX, '2026-09-21'));
        $reports->save($this->report(self::SAM, '2026-09-21'));
        $this->flushAndClear();
        $alex = new UserId(self::ALEX);

        $latest = $reports->latestOf($alex, 10);

        self::assertSame(['2026-09-21 00:00', '2026-09-14 00:00'], array_map(static fn (WeeklyReport $report): string => $report->weekStart()->format('Y-m-d H:i'), $latest));
        self::assertTrue($reports->has($alex, new \DateTimeImmutable('2026-09-21 12:00')));
        self::assertFalse($reports->has($alex, new \DateTimeImmutable('2026-09-28')));
        self::assertSame(Pace::Below, $latest[0]->verdict());
        self::assertSame('Continue ton programme.', $latest[0]->action());
        self::assertSame($this->report(self::ALEX, '2026-09-21')->figures()->toArray(), $latest[0]->figures()->toArray());
        self::assertSame(57, $latest[0]->adherence()->score(), '(14 + 14 + 100 + 100) / 4: one day on target of 7');

        // A page at a time: the weeks before the last one received.
        $page = $reports->latestOf($alex, 1, new \DateTimeImmutable('2026-09-21'));
        self::assertSame(['2026-09-14'], array_map(static fn (WeeklyReport $report): string => $report->weekStart()->format('Y-m-d'), $page));
        self::assertSame([], $reports->latestOf($alex, 1, new \DateTimeImmutable('2026-09-14')));
    }

    public function testAWeekHasOneReport(): void
    {
        $reports = self::getContainer()->get(WeeklyReportRepository::class);
        $reports->save($this->report(self::ALEX, '2026-09-21'));
        $this->flushAndClear();

        $reports->save($this->report(self::ALEX, '2026-09-21'));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
