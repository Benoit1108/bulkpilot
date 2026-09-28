<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class JournalEntryIdType extends UuidType
{
    public const string NAME = 'journal_entry_id';

    protected function identifierClass(): string
    {
        return JournalEntryId::class;
    }
}
