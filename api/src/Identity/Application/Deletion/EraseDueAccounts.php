<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Shared\Application\Bus\Command\Command;

/**
 * Sent by Identity's schedule (ADR-0030): the accounts whose deletion delay is over are erased.
 */
final readonly class EraseDueAccounts implements Command
{
}
