<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Domain\Progression\PersonalRecord;

final readonly class RecordOutput
{
    public const array KINDS = ['load', 'estimated_one_rep_max', 'volume', 'reps', 'duration', 'reps_at_load'];

    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => self::KINDS])]
        public string $kind,
        /** Kg (load, estimate, volume), reps or seconds. */
        #[ApiProperty(required: true)]
        public float $value,
        /** The load, for reps at a load. */
        #[ApiProperty(required: true)]
        public ?float $loadKg,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $date,
        #[ApiProperty(required: true)]
        public string $workoutId,
    ) {
    }

    public static function of(PersonalRecord $record): self
    {
        return new self($record->kind->value, $record->value, $record->loadKg, $record->date, $record->workoutId->value);
    }
}
