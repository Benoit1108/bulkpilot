<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

use App\Shared\Domain\Identifier\UserId;

/**
 * How an accepted Coach proposal changes the calories (ADR-0028), implemented by Nutrition: a new
 * target from a day on, recorded as coming from the Coach, the protein and fat kept and the
 * carbohydrates making up the rest. The Coach's only write.
 */
interface CalorieTargets
{
    /**
     * @param int $fromKcal the target the proposal was made against
     *
     * @throws TargetChanged     the target in force is no longer that one (or there is none)
     * @throws TargetUnreachable no valid target has these calories with the same protein and fat
     */
    public function adjust(UserId $userId, int $fromKcal, int $byKcal, \DateTimeImmutable $effectiveFrom): void;
}
