<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Shared\Domain\Identifier\Uuid;

/**
 * Chosen by the client (the workout may start offline, spec §116): writes are idempotent.
 */
final readonly class WorkoutId extends Uuid
{
}
