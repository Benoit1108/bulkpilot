<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

/**
 * A backup or its verification did not go through: loud on purpose (spec §106: an untested
 * backup is no strategy).
 */
final class BackupFailed extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
