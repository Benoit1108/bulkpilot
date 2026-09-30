<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Shared\Domain\Identifier\UserId;

/**
 * When each account's checks last ran, kept apart from the settings the user edits. Taking a turn
 * is atomic: of two checks at once (the schedule and the app opening), one runs, the other finds
 * nothing to do; neither fails.
 */
interface CheckLog
{
    /** An account is checked again this long after the last time at the earliest. */
    public const int INTERVAL_MINUTES = 5;

    /**
     * Takes the account's turn: false when it was checked less than {@see INTERVAL_MINUTES} ago,
     * or is being checked right now (the turn is held until that check's transaction ends).
     */
    public function takeTurn(UserId $userId, \DateTimeImmutable $now): bool;
}
