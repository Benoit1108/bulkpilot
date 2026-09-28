<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class RemoveEntry implements Command
{
    public function __construct(public JournalEntryId $entryId, public UserId $userId)
    {
    }
}
