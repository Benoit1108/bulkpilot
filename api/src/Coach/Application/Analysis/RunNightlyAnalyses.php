<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Bus\Command\Command;

/**
 * Sent by the Coach's schedule every quarter of an hour (ADR-0029): the accounts whose night it
 * is get their analysis, ready when they wake up (the weekly report is drawn Monday night).
 */
final readonly class RunNightlyAnalyses implements Command
{
}
