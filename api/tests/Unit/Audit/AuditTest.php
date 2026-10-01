<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Audit\Application\Activity\ActivityWording;
use App\Audit\Application\Activity\ListActivity;
use App\Audit\Application\Activity\ListActivityHandler;
use App\Audit\Application\PersonalData\AuditPersonalData;
use App\Audit\Application\Port\Pseudonyms;
use App\Audit\Application\RecordingAuditTrail;
use App\Audit\Application\Retention\PurgeAuditEvents;
use App\Audit\Application\Retention\PurgeAuditEventsHandler;
use App\Audit\Domain\Action;
use App\Audit\Domain\AuditEvent;
use App\Audit\Domain\AuditEventId;
use App\Audit\Domain\InvalidAuditEvent;
use App\Audit\Infrastructure\Pseudonym\HmacPseudonyms;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Audit\InMemoryAuditEventRepository;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AuditTest extends TestCase
{
    private const string ALEX = '0199a0b1-7c3e-7d4a-9b2f-00000000a001';
    private const string SAM = '0199a0b1-7c3e-7d4a-9b2f-00000000a002';

    private InMemoryAuditEventRepository $events;
    private TestHandler $log;
    private RecordingAuditTrail $trail;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->events = new InMemoryAuditEventRepository();
        $this->log = new TestHandler();
        $this->trail = new RecordingAuditTrail($this->events, new SequentialUuidGenerator(), new HmacPseudonyms('test-secret'), new Logger('audit', [$this->log]));
        $this->clock = new MockClock('2026-09-30 12:00:00');
    }

    public function testAnEventIsKeptForTheUserAndLoggedForTheOperator(): void
    {
        $this->trail->record(new UserId(self::ALEX), 'nutrition.target_set', ['previous_kcal' => 2200, 'kcal' => 2350, 'origin' => 'coach'], 'coach', $this->clock->now());

        [$view] = $this->list(self::ALEX);
        self::assertSame(['nutrition.target_set', ['previous_kcal' => 2200, 'kcal' => 2350, 'origin' => 'coach'], 'coach'], [$view->action, $view->details, $view->source]);
        self::assertTrue($this->log->hasInfoThatContains('nutrition.target_set'));
        self::assertSame(['user_id' => self::ALEX, 'source' => 'coach', 'occurred_on' => '2026-09-30T12:00:00+00:00', 'previous_kcal' => 2200, 'kcal' => 2350, 'origin' => 'coach'], $this->log->getRecords()[0]->context);
    }

    public function testTheActivityIsTheUsersOwnTheLatestFirstAPageAtATime(): void
    {
        foreach (['identity.signed_in', 'identity.password_changed', 'identity.two_factor_enabled'] as $action) {
            $this->trail->record(new UserId(self::ALEX), $action, [], 'user', $this->clock->now());
            $this->clock->sleep(60);
        }
        $this->trail->record(new UserId(self::SAM), 'identity.signed_in', [], 'user', $this->clock->now());

        $first = $this->list(self::ALEX, 2);
        self::assertSame(['identity.two_factor_enabled', 'identity.password_changed'], array_map(static fn ($view): string => $view->action, $first));
        $next = $this->list(self::ALEX, 2, $first[1]->occurredAt, new AuditEventId($first[1]->id));
        self::assertSame(['identity.signed_in'], array_map(static fn ($view): string => $view->action, $next));
    }

    public function testEventsOfTheSameSecondArePagedWithoutLosingAny(): void
    {
        foreach (['identity.registered', 'identity.email_verified', 'identity.signed_in'] as $action) {
            $this->trail->record(new UserId(self::ALEX), $action, [], 'user', $this->clock->now());
        }

        $seen = [];
        $before = null;
        $beforeId = null;
        do {
            $page = $this->list(self::ALEX, 1, $before, $beforeId);
            foreach ($page as $view) {
                $seen[] = $view->action;
                [$before, $beforeId] = [$view->occurredAt, new AuditEventId($view->id)];
            }
        } while ([] !== $page);
        self::assertEqualsCanonicalizing(['identity.registered', 'identity.email_verified', 'identity.signed_in'], $seen);
        self::assertCount(3, $seen);
    }

    public function testAnActionNamedByNoOneOrAnUnknownSourceIsAProgrammingError(): void
    {
        $this->expectException(InvalidAuditEvent::class);

        AuditEvent::record(new AuditEventId('0199a0b1-7c3e-7d4a-9b2f-00000000a0ff'), new UserId(self::ALEX), 'identity.signed_in', [], 'hacker', $this->clock->now());
    }

    public function testEventsOlderThanTheRetentionAreRemoved(): void
    {
        $this->trail->record(new UserId(self::ALEX), 'export.requested', [], 'user', $this->clock->now()->modify('-366 days'));
        $this->trail->record(new UserId(self::ALEX), 'export.downloaded', [], 'user', $this->clock->now()->modify('-364 days'));

        new PurgeAuditEventsHandler($this->events, $this->clock, new Logger('audit', [$this->log]), 365)(new PurgeAuditEvents());

        self::assertSame(['export.downloaded'], array_map(static fn ($view): string => $view->action, $this->list(self::ALEX)));
        self::assertTrue($this->log->hasInfoThatContains('audit.purged'));
    }

    public function testTheActivityIsExportedWithinThePeriod(): void
    {
        $this->trail->record(new UserId(self::ALEX), 'identity.signed_in', ['device' => 'Firefox'], 'user', new \DateTimeImmutable('2026-09-01 08:00:00'));
        $this->trail->record(new UserId(self::ALEX), 'identity.password_changed', [], 'user', new \DateTimeImmutable('2026-09-20 08:00:00'));
        $this->trail->record(new UserId(self::SAM), 'identity.signed_in', [], 'user', new \DateTimeImmutable('2026-09-20 08:00:00'));

        [$dataset] = $this->personalData()->export(new UserId(self::ALEX), new ExportScope(new \DateTimeImmutable('2026-09-10')));

        self::assertSame('activite', $dataset->name);
        self::assertSame(['Date', 'Action', 'Par', 'Détails'], $dataset->columns);
        self::assertCount(1, $dataset->rows);
        [$date, $action, $by, $details] = $dataset->rows[0];
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-09-20T08:00:00+00:00', $date->format(\DATE_ATOM));
        self::assertSame(['Mot de passe changé', 'Toi', null], [$action, $by, $details], 'in words, as the user reads it');
        self::assertSame('activity', $this->personalData()->domain());
        self::assertSame([], $this->personalData()->files(new UserId(self::ALEX), new ExportScope()));
    }

    public function testEveryActionIsWordedAndItsDetailsReadable(): void
    {
        foreach (Action::cases() as $action) {
            self::assertNotSame($action->value, ActivityWording::label($action->value), \sprintf('%s has its words', $action->value));
        }
        self::assertSame("2\u{202F}200 kcal → 2\u{202F}350 kcal, à partir du 2026-10-01, par le Coach", ActivityWording::details('nutrition.target_set', ['previous_kcal' => 2200, 'kcal' => 2350, 'effective_from' => '2026-10-01', 'origin' => 'coach']));
        self::assertSame('calories.increase, acceptée', ActivityWording::details('coach.recommendation_decided', ['kind' => 'calories.increase', 'status' => 'accepted']));
        self::assertSame('Firefox · Linux', ActivityWording::details('identity.signed_in', ['device' => 'Firefox · Linux']));
        self::assertNull(ActivityWording::details('identity.password_changed', []));
    }

    public function testAnUnknownActionIsRefused(): void
    {
        $this->expectException(InvalidAuditEvent::class);
        $this->trail->record(new UserId(self::ALEX), 'identity.made_up', [], 'user', $this->clock->now());
    }

    public function testTheOperatorLogNamesAnErasedAccountByItsPseudonymOnly(): void
    {
        $this->trail->record(new UserId(self::ALEX), 'identity.account_erased', [], 'system', $this->clock->now());

        $record = $this->log->getRecords()[0];
        self::assertArrayNotHasKey('user_id', $record->context);
        self::assertSame(new HmacPseudonyms('test-secret')->of(new UserId(self::ALEX)), $record->context['pseudonym'] ?? null);
    }

    public function testErasingKeepsWhatHappenedUnderAPseudonymOnly(): void
    {
        $this->trail->record(new UserId(self::ALEX), 'nutrition.target_set', ['kcal' => 2350], 'coach', $this->clock->now());
        $this->trail->record(new UserId(self::SAM), 'identity.signed_in', ['device' => 'Safari'], 'user', $this->clock->now());
        $pseudonyms = new HmacPseudonyms('the kernel secret');

        $this->personalData($pseudonyms)->erase(new UserId(self::ALEX));
        $this->personalData($pseudonyms)->erase(new UserId(self::ALEX));

        self::assertSame([], $this->list(self::ALEX));
        $erased = array_values(array_filter($this->events->events, static fn (AuditEvent $event): bool => null === $event->ownerId()));
        self::assertCount(1, $erased);
        self::assertSame(['nutrition.target_set', [], 'coach'], [$erased[0]->action(), $erased[0]->details(), $erased[0]->source()]);
        self::assertSame($pseudonyms->of(new UserId(self::ALEX)), $erased[0]->pseudonym());
        self::assertSame(['device' => 'Safari'], $this->list(self::SAM)[0]->details, 'the others keep theirs');
    }

    public function testThePseudonymIsStableButNeitherTheIdNorTheSameUnderAnotherSecret(): void
    {
        $alex = new UserId(self::ALEX);
        $pseudonym = new HmacPseudonyms('secret one')->of($alex);

        self::assertSame($pseudonym, new HmacPseudonyms('secret one')->of($alex));
        self::assertNotSame($pseudonym, new HmacPseudonyms('secret two')->of($alex));
        self::assertNotSame($pseudonym, new HmacPseudonyms('secret one')->of(new UserId(self::SAM)));
        self::assertStringNotContainsString(self::ALEX, $pseudonym);
        self::assertSame(64, \strlen($pseudonym));
    }

    /**
     * @return list<\App\Audit\Application\Activity\ActivityView>
     */
    private function list(string $user, int $limit = 20, ?\DateTimeImmutable $before = null, ?AuditEventId $beforeId = null): array
    {
        return new ListActivityHandler($this->events)(new ListActivity(new UserId($user), $limit, $before, $beforeId));
    }

    private function personalData(?Pseudonyms $pseudonyms = null): AuditPersonalData
    {
        return new AuditPersonalData($this->events, $pseudonyms ?? new HmacPseudonyms('the kernel secret'));
    }
}
