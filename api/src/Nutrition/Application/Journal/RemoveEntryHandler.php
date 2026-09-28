<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class RemoveEntryHandler implements CommandHandler
{
    public function __construct(private JournalEntryRepository $entries)
    {
    }

    public function __invoke(RemoveEntry $command): void
    {
        $this->entries->remove($this->entries->getOwnedBy($command->entryId, $command->userId));
    }
}
