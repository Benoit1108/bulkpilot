<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\WorkoutId;

/**
 * Records a set, or corrects it (same id).
 */
final readonly class RecordSet implements Command
{
    public function __construct(public WorkoutId $workoutId, public UserId $userId, public SetId $setId, public int $exerciseIndex, public SetKind $kind, public ?float $loadKg, public ?int $reps, public ?int $seconds, public ?int $rir, public \DateTimeImmutable $doneAt)
    {
    }
}
