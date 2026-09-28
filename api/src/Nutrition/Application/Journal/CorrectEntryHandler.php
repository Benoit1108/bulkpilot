<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\JournalDays;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class CorrectEntryHandler implements CommandHandler
{
    public function __construct(private JournalEntryRepository $entries, private JournalDays $days, private ClockInterface $clock)
    {
    }

    public function __invoke(CorrectEntry $command): void
    {
        $entry = $this->entries->getOwnedBy($command->entryId, $command->userId);
        $entry->correct($command->grams, $command->slot, $this->days->assertLoggable($command->day, $this->clock->now()));
        $this->entries->save($entry);
    }
}
