<?php

declare(strict_types=1);

namespace App\Progression\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class WeightGoalNotFound extends NotFound
{
    public static function current(): self
    {
        return new self('No weight goal set.');
    }

    public function errorCode(): string
    {
        return 'progression.weight_goal_not_found';
    }
}
