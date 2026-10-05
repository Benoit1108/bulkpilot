<?php

declare(strict_types=1);

namespace App\Tests\Integration\System;

use App\System\Application\Monitoring\RecordSchedulerBeat;
use App\System\Presentation\Scheduler\MonitoringSchedule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

final class MonitoringScheduleTest extends KernelTestCase
{
    public function testTheWorkerBeatsEveryQuarterOfAnHourOnTheJobBus(): void
    {
        $messages = [...self::getContainer()->get(MonitoringSchedule::class)->getSchedule()->getRecurringMessages()];

        self::assertSame(['every 15 minutes'], array_map(static fn (RecurringMessage $message): string => (string) $message->getTrigger(), $messages));
        $envelope = [...$messages[0]->getProvider()->getMessages(new MessageContext('monitoring', 'id', $messages[0]->getTrigger(), new \DateTimeImmutable()))][0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertInstanceOf(RecordSchedulerBeat::class, $envelope->getMessage());
        self::assertSame('job.bus', $envelope->last(BusNameStamp::class)?->getBusName());
    }
}
