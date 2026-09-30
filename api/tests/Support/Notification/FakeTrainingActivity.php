<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Shared\Application\Notifying\DayTraining;
use App\Shared\Application\Notifying\TrainingActivity;
use App\Shared\Domain\Identifier\UserId;

final class FakeTrainingActivity implements TrainingActivity
{
    /** @var array<string, DayTraining> by user id */
    public array $days = [];
    /** @var list<array{\DateTimeImmutable, \DateTimeImmutable}> the start of the day and the records' window asked */
    public array $asked = [];

    public function on(UserId $userId, \DateTimeImmutable $dayStart, \DateTimeImmutable $since): DayTraining
    {
        $this->asked[] = [$dayStart, $since];

        return $this->days[$userId->value] ?? new DayTraining(null, 0, false, []);
    }
}
