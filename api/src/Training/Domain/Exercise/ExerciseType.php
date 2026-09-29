<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

/**
 * Compound movements work several joints (squat, row); isolation ones a single joint (curl).
 */
enum ExerciseType: string
{
    case Compound = 'compound';
    case Isolation = 'isolation';
}
