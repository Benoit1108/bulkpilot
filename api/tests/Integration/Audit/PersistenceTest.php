<?php

declare(strict_types=1);

namespace App\Tests\Integration\Audit;

use App\Audit\Domain\AuditEvent;
use App\Audit\Domain\AuditEventId;
use App\Audit\Domain\AuditEventRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private const string ALEX = '0199a0b1-7c3e-7d4a-9b2f-00000000e001';
    private const string SAM = '0199a0b1-7c3e-7d4a-9b2f-00000000e002';

    private AuditEventRepository $events;
    private Connection $connection;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->events = self::getContainer()->get(AuditEventRepository::class);
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testAnEventIsWrittenAtOnceAndReadBackAsItWas(): void
    {
        $this->append(self::ALEX, 'nutrition.target_set', '2026-09-30 10:00:00', ['kcal' => 2350, 'origin' => 'coach', 'previous_kcal' => null], 'coach');

        // No flush: the row is there already (a read, like a failed sign-in, keeps its trace).
        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM audit_events'));
        [$event] = $this->events->latestOf(new UserId(self::ALEX), 10);
        self::assertSame(['nutrition.target_set', ['kcal' => 2350, 'origin' => 'coach', 'previous_kcal' => null], 'coach', '2026-09-30T10:00:00+00:00'], [$event->action(), $event->details(), $event->source(), $event->createdAt()->format(\DATE_ATOM)]);
    }

    public function testAnEventRecordedByAFailedCommandGoesWithIt(): void
    {
        $this->connection->beginTransaction();
        $this->append(self::ALEX, 'identity.password_changed', '2026-09-30 10:00:00');
        $this->connection->rollBack();

        self::assertSame([], $this->events->latestOf(new UserId(self::ALEX), 10));
    }

    public function testTheLatestComeFirstAndTheCursorKeepsTiesOfTheSameSecond(): void
    {
        foreach (['identity.registered', 'identity.email_verified', 'identity.signed_in'] as $action) {
            $this->append(self::ALEX, $action, '2026-09-30 10:00:00');
        }
        $this->append(self::ALEX, 'identity.password_changed', '2026-09-30 09:00:00');
        $this->append(self::SAM, 'identity.sign_in_failed', '2026-09-30 11:00:00');

        $seen = [];
        $before = null;
        $beforeId = null;
        while ([] !== ($page = $this->events->latestOf(new UserId(self::ALEX), 1, $before, $beforeId))) {
            $seen[] = $page[0]->action();
            [$before, $beforeId] = [$page[0]->createdAt(), $page[0]->id()];
        }

        self::assertSame(['identity.signed_in', 'identity.email_verified', 'identity.registered', 'identity.password_changed'], $seen, 'id order breaks the tie (ids grow)');
        self::assertSame(['identity.password_changed', 'identity.registered', 'identity.email_verified', 'identity.signed_in'], array_map(static fn (AuditEvent $event): string => $event->action(), $this->events->allOf(new UserId(self::ALEX))));
    }

    public function testPseudonymisingKeepsTheActionAndTheTimeOnly(): void
    {
        $this->append(self::ALEX, 'identity.signed_in', '2026-09-30 10:00:00', ['device' => 'Firefox']);
        $this->append(self::SAM, 'identity.signed_in', '2026-09-30 10:00:00', ['device' => 'Safari']);

        $this->events->pseudonymise(new UserId(self::ALEX), str_repeat('a', 64));

        self::assertSame([], $this->events->latestOf(new UserId(self::ALEX), 10));
        self::assertSame(
            ['owner_id' => null, 'pseudonym' => str_repeat('a', 64), 'action' => 'identity.signed_in', 'details' => '{}'],
            $this->connection->fetchAssociative('SELECT owner_id, pseudonym, action, details::text AS details FROM audit_events WHERE pseudonym IS NOT NULL'),
        );
        self::assertSame(['device' => 'Safari'], $this->events->latestOf(new UserId(self::SAM), 10)[0]->details());
    }

    public function testThePurgeRemovesWhatIsOlderThanTheRetention(): void
    {
        $this->append(self::ALEX, 'export.requested', '2025-09-29 23:59:59');
        $this->append(self::ALEX, 'export.downloaded', '2025-09-30 00:00:00');

        self::assertSame(1, $this->events->purgeBefore(new \DateTimeImmutable('2025-09-30 00:00:00')));
        self::assertSame(['export.downloaded'], array_map(static fn (AuditEvent $event): string => $event->action(), $this->events->allOf(new UserId(self::ALEX))));
    }

    /**
     * @param array<string, string|int|float|bool|null> $details
     */
    private function append(string $owner, string $action, string $at, array $details = [], string $source = 'user'): void
    {
        $this->events->append(AuditEvent::record(
            new AuditEventId(\sprintf('0199a0b1-7c3e-7d4a-9b2f-%012d', ++$this->ids)),
            new UserId($owner),
            $action,
            $details,
            $source,
            new \DateTimeImmutable($at),
        ));
    }
}
