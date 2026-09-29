<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Domain\Workout\WorkoutSet;

final readonly class WorkoutSetOutput
{
    public const array KINDS = ['warmup', 'approach', 'normal', 'effective', 'drop'];

    public function __construct(
        #[ApiProperty(required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public int $exerciseIndex,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => self::KINDS])]
        public string $kind,
        /** Kg; for a bodyweight exercise, what is added to the body. */
        #[ApiProperty(required: true)]
        public ?float $loadKg,
        #[ApiProperty(required: true)]
        public ?int $reps,
        #[ApiProperty(required: true)]
        public ?int $seconds,
        #[ApiProperty(required: true)]
        public ?int $rir,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $doneAt,
    ) {
    }

    public static function of(WorkoutSet $set): self
    {
        return new self($set->id->value, $set->exerciseIndex, $set->kind->value, $set->loadKg, $set->reps, $set->seconds, $set->rir, $set->doneAt);
    }
}
