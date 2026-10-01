<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

/**
 * What a test restore found: the tables, the migration, how many rows the key tables hold, and
 * how many accounts erased since would come back with it (to erase again after a real restore).
 */
final readonly class RestoreCheck
{
    /**
     * @param array<string, int> $rows by key table
     */
    public function __construct(public int $tables, public string $migration, public array $rows, public int $erasedToReapply)
    {
    }

    public function accounts(): int
    {
        return $this->rows['users'] ?? 0;
    }
}
