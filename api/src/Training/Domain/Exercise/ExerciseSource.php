<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

enum ExerciseSource: string
{
    /** The shared library shipped with the application: read-only, refreshed by its import. */
    case Library = 'library';
    /** A user's own exercise: theirs to edit or delete. */
    case Custom = 'custom';
}
