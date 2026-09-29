<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

use App\Shared\Domain\Exception\DomainException;

/**
 * The change would make a target Nutrition refuses (calories out of bounds, or too few left for
 * the protein and fat kept).
 */
final class TargetUnreachable extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'coach.target_unreachable';
    }
}
