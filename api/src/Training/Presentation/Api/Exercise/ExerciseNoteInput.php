<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use App\Training\Domain\Exercise\ExerciseNote;
use Symfony\Component\Validator\Constraints as Assert;

final class ExerciseNoteInput
{
    /** Blank (or null) removes the note. */
    #[Assert\Length(max: ExerciseNote::MAX_LENGTH, normalizer: 'trim')]
    public ?string $text = null;
}
