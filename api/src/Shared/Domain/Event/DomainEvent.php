<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Something that happened in the domain, named in the past tense
 * (WorkoutCompleted, WeightMeasurementRecorded, RecommendationAccepted...).
 *
 * Events are the preferred way for bounded contexts to react to each other.
 */
interface DomainEvent
{
    public function occurredOn(): \DateTimeImmutable;
}
