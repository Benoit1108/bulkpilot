<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Domain;

use App\Coach\Domain\Exception\RecommendationClosed;
use App\Coach\Domain\Recommendation\CoachMessage;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationDecided;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Coach\Domain\Recommendation\RecommendationMade;
use App\Coach\Domain\Recommendation\Status;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Tests\Support\Coach\CoachTestCase;

final class RecommendationTest extends CoachTestCase
{
    private static function increase(int $from = 2200, int $by = 150): Finding
    {
        return new Finding(Kind::IncreaseCalories, Level::Priority, ['targetKcal' => $from], new CalorieProposal($from, $by));
    }

    public function testANewRecommendationIsPendingForSevenDaysWithWhatLedToIt(): void
    {
        $recommendation = $this->recommendation(self::increase());
        $now = $this->clock->now();

        self::assertSame(Status::Pending, $recommendation->status($now));
        self::assertTrue($recommendation->isShown($now));
        self::assertSame(['targetKcal' => 2200], $recommendation->figures());
        self::assertSame(2350, $recommendation->proposal()?->toKcal());
        self::assertSame('2026-10-06 08:00', $recommendation->expiresAt()->format('Y-m-d H:i'));
        self::assertSame('fake', $recommendation->message()->provider);
        self::assertNull($recommendation->decidedAt());
    }

    public function testANewRecommendationIsAnnouncedWithItsProvider(): void
    {
        $recommendation = Recommendation::make(
            new RecommendationId('00000000-0000-7000-8000-0000000000aa'),
            $this->alex,
            self::increase(),
            new CoachMessage('Titre', 'Corps', ['Pourquoi'], 'Action', 'fake'),
            $this->clock->now(),
        );

        $events = $recommendation->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RecommendationMade::class, $events[0]);
        self::assertSame(['00000000-0000-7000-8000-0000000000aa', Kind::IncreaseCalories, 'fake'], [$events[0]->recommendationId->value, $events[0]->kind, $events[0]->provider]);
        self::assertSame('Action', $recommendation->message()->action);
    }

    public function testAcceptingIsKeptAndAnnounced(): void
    {
        $recommendation = $this->recommendation(self::increase());

        $recommendation->accept($this->clock->now());

        self::assertSame(Status::Accepted, $recommendation->status($this->clock->now()));
        self::assertSame('2026-09-29 08:00:00', $recommendation->decidedAt()?->format('Y-m-d H:i:s'));
        self::assertFalse($recommendation->isOpen($this->clock->now()));
        $events = $recommendation->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RecommendationDecided::class, $events[0]);
        self::assertSame(Status::Accepted, $events[0]->status);
        self::assertSame(Kind::IncreaseCalories, $events[0]->kind);
        self::assertTrue($events[0]->userId->equals($this->alex));
    }

    public function testADecidedRecommendationCannotBeDecidedAgain(): void
    {
        $recommendation = $this->recommendation(self::increase());
        $recommendation->reject($this->clock->now());

        $this->expectException(RecommendationClosed::class);
        $recommendation->accept($this->clock->now());
    }

    public function testPostponedItIsHiddenTwoDaysThenOfferedAgain(): void
    {
        $recommendation = $this->recommendation(self::increase());
        $recommendation->postpone($this->clock->now());

        self::assertSame(Status::Postponed, $recommendation->status($this->clock->now()));
        self::assertTrue($recommendation->isOpen($this->clock->now()));
        self::assertFalse($recommendation->isShown($this->clock->now()));

        $this->clock->modify('+1 day +23 hours');
        self::assertFalse($recommendation->isShown($this->clock->now()));
        $this->clock->modify('+1 hour');
        self::assertTrue($recommendation->isShown($this->clock->now()));

        $recommendation->accept($this->clock->now());
        self::assertSame(Status::Accepted, $recommendation->status($this->clock->now()));
    }

    public function testAfterSevenDaysItIsExpiredAndCannotBeAccepted(): void
    {
        $recommendation = $this->recommendation(self::increase());
        $this->clock->modify('+7 days');

        self::assertSame(Status::Expired, $recommendation->status($this->clock->now()));
        self::assertFalse($recommendation->isOpen($this->clock->now()));

        $this->expectException(RecommendationClosed::class);
        $recommendation->accept($this->clock->now());
    }

    public function testExpiringLeavesADecidedOneAsItIs(): void
    {
        $pending = $this->recommendation(self::increase());
        $accepted = $this->recommendation(self::increase());
        $accepted->accept($this->clock->now());

        $pending->expire($this->clock->now());
        $accepted->expire($this->clock->now());

        self::assertSame(Status::Expired, $pending->status($this->clock->now()));
        self::assertSame(Status::Accepted, $accepted->status($this->clock->now()));
        self::assertSame([], $pending->pullDomainEvents(), 'an expiry is not a decision');
    }

    public function testItMatchesAFindingOfTheSameKindAndProposal(): void
    {
        $recommendation = $this->recommendation(self::increase());
        $advice = $this->recommendation(new Finding(Kind::LowProtein, Level::Watch, ['averageProtein' => 90.0]));

        self::assertTrue($recommendation->matches(self::increase()));
        self::assertFalse($recommendation->matches(self::increase(2200, 100)), 'another step');
        self::assertFalse($recommendation->matches(self::increase(2350)), 'against another target');
        self::assertFalse($recommendation->matches(new Finding(Kind::DecreaseCalories, Level::Priority, [], new CalorieProposal(2200, 150))));
        self::assertTrue($advice->matches(new Finding(Kind::LowProtein, Level::Watch, ['averageProtein' => 80.0])), 'advice: the kind is enough');
        self::assertFalse($advice->matches(new Finding(Kind::Stall, Level::Watch, [])));
    }
}
