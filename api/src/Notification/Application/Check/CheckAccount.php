<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * The scheduled checks of one account (asynchronous: one message each, so that one failing is
 * retried alone).
 */
final readonly class CheckAccount implements Command
{
    public function __construct(public UserId $userId, public string $timeZone)
    {
    }
}
