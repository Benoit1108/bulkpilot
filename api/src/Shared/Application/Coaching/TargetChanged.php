<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

use App\Shared\Domain\Exception\Conflict;

/**
 * The target changed since the Coach made its proposal: applying it would build on a value the
 * user no longer has.
 */
final class TargetChanged extends Conflict
{
    public static function since(int $fromKcal): self
    {
        return new self(\sprintf('The target is no longer %d kcal.', $fromKcal));
    }

    public function errorCode(): string
    {
        return 'coach.target_changed';
    }
}
