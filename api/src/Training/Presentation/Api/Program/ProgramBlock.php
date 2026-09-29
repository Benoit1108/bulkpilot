<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\ApiProperty;

/**
 * One exercise, or a superset of several done back to back.
 */
final readonly class ProgramBlock
{
    /**
     * @param list<ProgramExercise> $exercises
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public array $exercises,
    ) {
    }
}
