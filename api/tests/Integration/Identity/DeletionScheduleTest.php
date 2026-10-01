<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Deletion\EraseDueAccounts;
use App\Identity\Presentation\Scheduler\IdentitySchedule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

final class DeletionScheduleTest extends KernelTestCase
{
    public function testDueAccountsAreLookedForEveryHour(): void
    {
        $messages = [...self::getContainer()->get(IdentitySchedule::class)->getSchedule()->getRecurringMessages()];

        self::assertSame(['every 1 hour'], array_map(static fn (RecurringMessage $message): string => (string) $message->getTrigger(), $messages));
        self::assertInstanceOf(EraseDueAccounts::class, [...$messages[0]->getProvider()->getMessages(new MessageContext('identity', 'id', $messages[0]->getTrigger(), new \DateTimeImmutable()))][0]);
    }
}
