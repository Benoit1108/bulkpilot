<?php

declare(strict_types=1);

namespace App\Coach\Domain\Recommendation;

/**
 * Where a recommendation stands (spec §70).
 */
enum Status: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Postponed = 'postponed';
    /** No longer offered: too old, or the data stopped supporting it. */
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return self::Pending === $this || self::Postponed === $this;
    }
}
