<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Traces a refused proof of identity, in its own transaction: the command that refused it rolled
 * back, and a query (checking a password) writes nothing.
 */
final readonly class RecordAuthenticationFailure implements Command
{
    public function __construct(public UserId $userId, public AuthenticationFailure $failure)
    {
    }
}
