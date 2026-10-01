<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Coach\Application\Analysis\AnalyseAccount;
use App\Coach\Application\Analysis\RunNightlyAnalyses;
use App\Coach\Presentation\Scheduler\CoachSchedule;
use App\Notification\Application\Check\CheckAccount;
use App\Notification\Application\Check\RunScheduledChecks;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationCreated;
use App\Notification\Domain\Notification\NotificationRepository;
use App\Notification\Presentation\Scheduler\NotificationSchedule;
use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Notifying\DayEating;
use App\Shared\Application\Notifying\EatingReadings;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Notification\FakeEatingReadings;
use App\Tests\Support\Shared\FakeAccounts;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * The schedules and the worker's queue (ADR-0029): the scheduled run sends one message per
 * account to the asynchronous transport instead of handling them in line.
 */
final class ScheduleTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string ALEX = '0199a0b1-7c3e-7d4a-9b2f-00000000e001';
    private const string SAM = '0199a0b1-7c3e-7d4a-9b2f-00000000e002';

    public function testBothSchedulesRunEveryQuarterOfAnHour(): void
    {
        $notifications = self::getContainer()->get(NotificationSchedule::class)->getSchedule()->getRecurringMessages();
        $coach = self::getContainer()->get(CoachSchedule::class)->getSchedule()->getRecurringMessages();

        self::assertSame(['every 15 minutes'], array_map(static fn (RecurringMessage $message): string => (string) $message->getTrigger(), [...$notifications]));
        self::assertInstanceOf(RunScheduledChecks::class, [...[...$notifications][0]->getProvider()->getMessages(new MessageContext('notifications', 'id', [...$notifications][0]->getTrigger(), new \DateTimeImmutable()))][0]);
        self::assertSame(['every 15 minutes'], array_map(static fn (RecurringMessage $message): string => (string) $message->getTrigger(), [...$coach]));
        self::assertInstanceOf(RunNightlyAnalyses::class, [...[...$coach][0]->getProvider()->getMessages(new MessageContext('coach', 'id', [...$coach][0]->getTrigger(), new \DateTimeImmutable()))][0]);
    }

    public function testTheScheduledRunQueuesOneCheckPerAccount(): void
    {
        $container = self::getContainer();
        $accounts = new FakeAccounts();
        $accounts->add('0199a0b1-7c3e-7d4a-9b2f-00000000e001', 'Europe/Paris');
        $accounts->add('0199a0b1-7c3e-7d4a-9b2f-00000000e002', 'Asia/Tokyo');
        $container->set(Accounts::class, $accounts);
        /** @var InMemoryTransport $async */
        $async = $container->get('messenger.transport.async');

        $container->get(CommandBus::class)->dispatch(new RunScheduledChecks());

        self::assertSame(
            [['0199a0b1-7c3e-7d4a-9b2f-00000000e001', 'Europe/Paris'], ['0199a0b1-7c3e-7d4a-9b2f-00000000e002', 'Asia/Tokyo']],
            array_map(static fn (Envelope $envelope): array => ($check = $envelope->getMessage()) instanceof CheckAccount ? [$check->userId->value, $check->timeZone] : [], $async->getSent()),
            'queued for the worker, not handled in line',
        );
    }

    public function testTheNightlyRunQueuesTheAnalysesOfTheAccountsAtThreeForTheWorker(): void
    {
        self::mockTime('2026-09-30 01:05:00 UTC');
        $container = self::getContainer();
        $accounts = new FakeAccounts();
        $accounts->add(self::ALEX, 'Europe/Paris');
        $accounts->add(self::SAM, 'America/New_York');
        $container->set(Accounts::class, $accounts);
        /** @var InMemoryTransport $async */
        $async = $container->get('messenger.transport.async');

        $container->get(CommandBus::class)->dispatch(new RunNightlyAnalyses());

        self::assertSame(
            [[self::ALEX, '2026-09-30']],
            array_map(static fn (Envelope $envelope): array => ($analysis = $envelope->getMessage()) instanceof AnalyseAccount ? [$analysis->userId->value, $analysis->today->format('Y-m-d')] : [], $async->getSent()),
            '03:05 in Paris, not yet 22:00 in New York; queued, not run in line',
        );
    }

    public function testACheckTheWorkerGetsTwiceMakesOneNotificationAndOnePush(): void
    {
        self::mockTime('2026-09-30 18:30:00 UTC');
        $container = self::getContainer();
        $eating = new FakeEatingReadings();
        $eating->days[self::ALEX] = new DayEating(1000.0, 2350, new \DateTimeImmutable('2026-09-30 10:30:00 UTC'));
        $container->set(EatingReadings::class, $eating);
        $accounts = new FakeAccounts();
        $accounts->add(self::ALEX, 'Europe/Paris');
        $container->set(Accounts::class, $accounts);
        /** @var InMemoryTransport $async */
        $async = $container->get('messenger.transport.async');
        $bus = $container->get('command.bus');
        $received = static fn (): Envelope => new Envelope(new CheckAccount(new UserId(self::ALEX), 'Europe/Paris'), [new ReceivedStamp('async')]);

        $bus->dispatch($received());
        self::mockTime('2026-09-30 18:45:00 UTC');
        $bus->dispatch($received());

        $notifications = $container->get(NotificationRepository::class)->latestOf(new UserId(self::ALEX), 10);
        self::assertSame(['low_calories:2026-09-30'], array_map(static fn (Notification $notification): string => $notification->dedupKey(), $notifications));
        self::assertCount(1, array_filter($async->getSent(), static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof NotificationCreated), 'one push, queued after the commit');
    }
}
