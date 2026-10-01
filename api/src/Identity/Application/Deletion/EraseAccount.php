<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Erases one account whose deletion delay is over (asynchronous). `$restored`: an account erased
 * before, brought back by restoring a backup (ADR-0031): erased again whatever its state.
 */
final readonly class EraseAccount implements Command
{
    public function __construct(public UserId $userId, public bool $restored = false)
    {
    }
}
