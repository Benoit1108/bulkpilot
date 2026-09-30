<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

use App\Shared\Domain\Identifier\UserId;

/**
 * Whether the Coach has a new weekly report (ADR-0029), implemented by Coach.
 */
interface CoachReports
{
    /** The Monday of the latest weekly report, null without one. */
    public function latestWeek(UserId $userId): ?\DateTimeImmutable;
}
