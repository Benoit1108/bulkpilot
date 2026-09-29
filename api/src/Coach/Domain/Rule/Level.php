<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

/**
 * How much a recommendation matters (spec §68): 🔴 prioritaire, 🟠 à surveiller, 🟢 information.
 */
enum Level: string
{
    case Priority = 'priority';
    case Watch = 'watch';
    case Info = 'info';

    /** The most important first. */
    public function rank(): int
    {
        return match ($this) {
            self::Priority => 0,
            self::Watch => 1,
            self::Info => 2,
        };
    }
}
