<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Shared\Application\Coaching\BodyWeight;
use App\Shared\Application\Coaching\WeightReading;
use App\Shared\Domain\Identifier\UserId;

/**
 * One user's weight: the same reading every day, unless one is given for that day; nothing for anyone else.
 */
final class FakeBodyWeight implements BodyWeight
{
    /** @var array<string, WeightReading> by day */
    public array $byDay = [];

    public function __construct(private readonly string $owner, public WeightReading $reading = new WeightReading(null, null, 0, 0))
    {
    }

    public function reading(UserId $userId, \DateTimeImmutable $day): WeightReading
    {
        if ($userId->value !== $this->owner) {
            return new WeightReading(null, null, 0, 0);
        }

        return $this->byDay[$day->format('Y-m-d')] ?? $this->reading;
    }
}
