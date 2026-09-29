<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Application\Coaching\TrainingReadings;
use App\Shared\Domain\Identifier\UserId;

/**
 * One user's training: the same reading for any range (the ranges asked are kept); nothing for anyone else.
 */
final class FakeTrainingReadings implements TrainingReadings
{
    /** @var list<array{string, string}> */
    public array $asked = [];

    public function __construct(private readonly string $owner, public TrainingReading $reading = new TrainingReading(0, null, 0, []))
    {
    }

    public function between(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): TrainingReading
    {
        if ($userId->value !== $this->owner) {
            return new TrainingReading(0, null, 0, []);
        }
        $this->asked[] = [$from->format('Y-m-d'), $to->format('Y-m-d')];

        return $this->reading;
    }
}
