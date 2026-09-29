<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

/**
 * Codes accepted and documented by the API (attributes need literal lists; a test keeps them
 * equal to the domain enums).
 */
final class ExerciseChoices
{
    public const array MUSCLES = ['chest', 'lats', 'upper_back', 'traps', 'lower_back', 'front_delts', 'side_delts', 'rear_delts', 'biceps', 'triceps', 'forearms', 'abs', 'obliques', 'glutes', 'quads', 'hamstrings', 'adductors', 'calves'];
    public const array EQUIPMENT = ['barbell', 'dumbbell', 'ez_bar', 'machine', 'cable', 'smith_machine', 'kettlebell', 'band', 'bodyweight', 'other'];
    public const array TYPES = ['compound', 'isolation'];
    public const array MEASUREMENTS = ['weight', 'bodyweight', 'duration'];
    public const array STRATEGIES = ['double_progression', 'linear', 'fixed_load', 'fixed_reps', 'target_rir'];
}
