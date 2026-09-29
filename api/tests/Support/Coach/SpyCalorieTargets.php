<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Shared\Application\Coaching\CalorieTargets;
use App\Shared\Application\Coaching\TargetChanged;
use App\Shared\Application\Coaching\TargetUnreachable;
use App\Shared\Domain\Identifier\UserId;

/**
 * Keeps each adjustment asked; refuses them all once the target "changed" or became "unreachable".
 */
final class SpyCalorieTargets implements CalorieTargets
{
    /** @var list<array{string, int, int, string}> user, from, by, effective from */
    public array $adjusted = [];
    public bool $changed = false;
    public bool $unreachable = false;

    public function adjust(UserId $userId, int $fromKcal, int $byKcal, \DateTimeImmutable $effectiveFrom): void
    {
        if ($this->changed) {
            throw TargetChanged::since($fromKcal);
        }
        if ($this->unreachable) {
            throw TargetUnreachable::because('out of bounds');
        }
        $this->adjusted[] = [$userId->value, $fromKcal, $byKcal, $effectiveFrom->format('Y-m-d')];
    }
}
