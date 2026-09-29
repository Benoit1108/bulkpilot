<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Application;

use App\Coach\Application\Audit\LogRecommendationDecision;
use App\Coach\Application\Recommendation\DecideRecommendation;
use App\Coach\Application\Recommendation\DecideRecommendationHandler;
use App\Coach\Application\Recommendation\Decision;
use App\Coach\Application\Recommendation\GetRecommendation;
use App\Coach\Application\Recommendation\GetRecommendationHandler;
use App\Coach\Application\Recommendation\ListRecommendations;
use App\Coach\Application\Recommendation\ListRecommendationsHandler;
use App\Coach\Application\Recommendation\RecommendationView;
use App\Coach\Domain\Exception\RecommendationNotFound;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationDecided;
use App\Coach\Domain\Recommendation\Status;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Shared\Application\Coaching\TargetChanged;
use App\Shared\Application\Coaching\TargetUnreachable;
use App\Tests\Support\Coach\CoachTestCase;
use App\Tests\Support\Coach\InMemoryRecommendationRepository;
use Monolog\Logger;

final class DecideRecommendationTest extends CoachTestCase
{
    private function proposal(int $by = 150): Recommendation
    {
        return $this->recommendation(new Finding(Kind::IncreaseCalories, Level::Priority, ['targetKcal' => 2200], new CalorieProposal(2200, $by)));
    }

    private function decide(Recommendation $recommendation, Decision $decision, string $today = self::TODAY): void
    {
        (new DecideRecommendationHandler($this->recommendations, $this->targets, $this->events, $this->clock))(
            new DecideRecommendation($recommendation->id(), $this->alex, $decision, self::day($today)),
        );
    }

    // Spec §132: nothing changes until the user accepts; then a new target from tomorrow.
    public function testAcceptingACalorieProposalSetsANewTargetFromTomorrow(): void
    {
        $recommendation = $this->proposal();

        $this->decide($recommendation, Decision::Accept);

        self::assertSame([[self::ALEX, 2200, 150, '2026-09-30']], $this->targets->adjusted);
        self::assertSame(Status::Accepted, InMemoryRecommendationRepository::stored($recommendation));
        self::assertSame([RecommendationDecided::class], $this->events->publishedClasses());
    }

    public function testTomorrowIsTheUsersTomorrow(): void
    {
        // A device a day ahead of the server (past midnight east of it), or behind.
        $this->decide($this->proposal(), Decision::Accept, '2026-09-30');
        $this->decide($this->proposal(-100), Decision::Accept, '2026-09-28');

        self::assertSame([[self::ALEX, 2200, 150, '2026-10-01'], [self::ALEX, 2200, -100, '2026-09-29']], $this->targets->adjusted);
    }

    public function testAcceptingAdviceChangesNothingElse(): void
    {
        $advice = $this->recommendation(new Finding(Kind::LowProtein, Level::Watch, []));

        $this->decide($advice, Decision::Accept);

        self::assertSame([], $this->targets->adjusted);
        self::assertSame(Status::Accepted, InMemoryRecommendationRepository::stored($advice));
    }

    public function testRejectingOrPostponingAProposalChangesNoTarget(): void
    {
        $rejected = $this->proposal();
        $postponed = $this->proposal();

        $this->decide($rejected, Decision::Reject);
        $this->decide($postponed, Decision::Postpone);

        self::assertSame([], $this->targets->adjusted);
        self::assertSame(Status::Rejected, InMemoryRecommendationRepository::stored($rejected));
        self::assertSame(Status::Postponed, InMemoryRecommendationRepository::stored($postponed));
        self::assertSame('2026-10-01 08:00', $postponed->postponedUntil()?->format('Y-m-d H:i'));
        self::assertCount(2, $this->events->published);
    }

    public function testATargetChangedSinceTheProposalIsRefusedAndNothingIsAnnounced(): void
    {
        $this->targets->changed = true;

        try {
            $this->decide($this->proposal(), Decision::Accept);
            self::fail('The change was applied.');
        } catch (TargetChanged) {
        }

        // The command's transaction rolls the decision back; nothing was published nor changed.
        self::assertSame([], $this->events->published);
        self::assertSame([], $this->targets->adjusted);
    }

    public function testAnUnreachableTargetIsRefused(): void
    {
        $this->targets->unreachable = true;

        $this->expectException(TargetUnreachable::class);
        $this->decide($this->proposal(), Decision::Accept);
    }

    public function testNoOneDecidesOnSomeoneElsesRecommendation(): void
    {
        $sams = $this->recommendation(new Finding(Kind::LowProtein, Level::Watch, []), $this->sam);

        $this->expectException(RecommendationNotFound::class);
        $this->decide($sams, Decision::Accept);
    }

    public function testEveryDecisionIsAudited(): void
    {
        $recommendation = $this->proposal();
        $this->decide($recommendation, Decision::Reject);
        $event = $this->events->published[0];
        self::assertInstanceOf(RecommendationDecided::class, $event);

        (new LogRecommendationDecision(new Logger('audit', [$this->audit])))($event);

        self::assertTrue($this->audit->hasInfoThatContains('coach.recommendation_decided'));
        self::assertSame([
            'user_id' => self::ALEX,
            'recommendation_id' => $recommendation->id()->value,
            'kind' => 'calories.increase',
            'status' => 'rejected',
            'occurred_on' => '2026-09-29T08:00:00+00:00',
        ], $this->audit->getRecords()[0]->context);
    }

    public function testTheHistoryShowsTheStatusAsOfNowTheLatestFirst(): void
    {
        $old = $this->proposal();
        $this->clock->modify('+8 days');
        $recent = $this->recommendation(new Finding(Kind::Records, Level::Info, []));

        $history = (new ListRecommendationsHandler($this->recommendations, $this->clock))(new ListRecommendations($this->alex, 20, null));

        self::assertSame([$recent->id()->value, $old->id()->value], array_map(static fn (RecommendationView $view): string => $view->id, $history));
        self::assertSame([Status::Pending, Status::Expired], array_map(static fn (RecommendationView $view): Status => $view->status, $history));
        $page = (new ListRecommendationsHandler($this->recommendations, $this->clock))(new ListRecommendations($this->alex, 500, $recent->createdAt()));
        self::assertCount(1, $page);
        self::assertSame(Status::Expired, (new GetRecommendationHandler($this->recommendations, $this->clock))(new GetRecommendation($old->id(), $this->alex))->status);
    }

    public function testAPageStartsAfterTheLastOneEvenAtTheSameTime(): void
    {
        // One analysis: three recommendations made at the same time.
        $made = [
            $this->recommendation(new Finding(Kind::LowProtein, Level::Watch, [])),
            $this->recommendation(new Finding(Kind::Stall, Level::Watch, [])),
            $this->recommendation(new Finding(Kind::Records, Level::Info, [])),
        ];
        $list = new ListRecommendationsHandler($this->recommendations, $this->clock);
        $first = $list(new ListRecommendations($this->alex, 2));
        $next = $list(new ListRecommendations($this->alex, 2, $first[1]->createdAt, $first[1]->id));

        self::assertSame([$made[2]->id()->value, $made[1]->id()->value], self::ids($first), 'the latest first, then by id');
        self::assertSame([$made[0]->id()->value], self::ids($next));
    }

    /**
     * @param list<RecommendationView> $views
     *
     * @return list<string>
     */
    private static function ids(array $views): array
    {
        return array_map(static fn (RecommendationView $view): string => $view->id, $views);
    }
}
