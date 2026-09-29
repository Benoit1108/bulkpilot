<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Domain\Progression\Suggestion;

/**
 * What to aim for today, from last time and the exercise's progression strategy. Only a proposal.
 */
final readonly class SuggestionOutput
{
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['increase_load', 'add_reps', 'repeat', 'decrease_load']])]
        public string $reason,
        #[ApiProperty(required: true)]
        public ?float $loadKg,
        #[ApiProperty(required: true)]
        public ?int $reps,
        #[ApiProperty(required: true)]
        public ?int $seconds,
    ) {
    }

    public static function of(?Suggestion $suggestion): ?self
    {
        return null === $suggestion ? null : new self($suggestion->reason->value, $suggestion->loadKg, $suggestion->reps, $suggestion->seconds);
    }
}
