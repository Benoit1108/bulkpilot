<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use Symfony\Component\Validator\Constraints as Assert;

final class AddWorkoutExerciseInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $exerciseId = '';
}
