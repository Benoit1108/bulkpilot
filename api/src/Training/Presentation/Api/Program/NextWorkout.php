<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Training\Application\Program\NextWorkoutView;

/**
 * The next day of the active program (spec §13: « prochain entraînement », « bouton démarrer »).
 */
#[ApiResource(
    shortName: 'NextWorkout',
    operations: [
        new Get(
            uriTemplate: '/training/next-workout',
            description: 'The day after the one of the latest workout of the active program (the first day when none); 404 without an active program.',
            provider: NextWorkoutProvider::class,
            openapi: new Operation(responses: ['404' => new Response('No active program (`training.no_active_program`).')]),
        ),
    ],
)]
final readonly class NextWorkout
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $programId,
        #[ApiProperty(required: true)]
        public string $programName,
        #[ApiProperty(description: 'The current version, the one a workout started now follows.', required: true)]
        public int $versionNumber,
        #[ApiProperty(required: true)]
        public int $dayIndex,
        #[ApiProperty(required: true)]
        public string $dayName,
        #[ApiProperty(required: true)]
        public int $exerciseCount,
        #[ApiProperty(description: 'When this day of the current version was last started (among the last 60 workouts); the PWA shows it in the user\'s day.', required: true)]
        public ?\DateTimeImmutable $lastDoneAt,
        #[ApiProperty(description: 'A workout is in progress: resume it rather than start another one.', required: true)]
        public bool $inProgress,
    ) {
    }

    public static function of(NextWorkoutView $view): self
    {
        return new self($view->programId, $view->programName, $view->versionNumber, $view->dayIndex, $view->dayName, $view->exerciseCount, $view->lastDoneAt, $view->inProgress);
    }
}
