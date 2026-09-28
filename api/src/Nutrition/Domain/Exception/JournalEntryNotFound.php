<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class JournalEntryNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Journal entry "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'nutrition.journal_entry_not_found';
    }
}
