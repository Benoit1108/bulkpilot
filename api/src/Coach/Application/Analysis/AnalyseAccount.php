<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * The nightly analysis of one account (asynchronous: one failing is retried alone).
 */
final readonly class AnalyseAccount implements Command
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $today)
    {
    }
}
