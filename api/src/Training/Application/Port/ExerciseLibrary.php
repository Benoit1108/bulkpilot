<?php

declare(strict_types=1);

namespace App\Training\Application\Port;

use App\Training\Application\Library\LibraryExercise;

/**
 * The exercises shipped with the application.
 */
interface ExerciseLibrary
{
    /**
     * @return iterable<LibraryExercise>
     */
    public function read(): iterable;
}
