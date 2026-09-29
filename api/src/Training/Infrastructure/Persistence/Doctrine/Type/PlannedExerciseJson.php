<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;
use App\Training\Domain\Program\PlannedExercise;

/**
 * A planned exercise as JSON, in program versions and workouts. Read back without constructors:
 * what was valid when written stays readable if a rule is tightened later.
 */
final class PlannedExerciseJson
{
    /**
     * @return array<string, mixed>
     */
    public static function encode(PlannedExercise $planned): array
    {
        return [
            'exerciseId' => $planned->exerciseId->value,
            'sets' => $planned->sets,
            'repMin' => $planned->repRange->min,
            'repMax' => $planned->repRange->max,
            'targetRir' => $planned->targetRir,
            'restSeconds' => $planned->restSeconds,
            'strategy' => $planned->strategy->value,
            'incrementKg' => $planned->incrementKg,
            'warmupSets' => $planned->warmupSets,
            'dropSet' => $planned->dropSet,
            'tempo' => $planned->tempo?->value,
            'notes' => $planned->notes,
        ];
    }

    /**
     * @throws \TypeError|\ValueError when malformed
     */
    public static function decode(mixed $planned): PlannedExercise
    {
        if (!\is_array($planned)) {
            throw new \TypeError('malformed exercise');
        }
        $int = static fn (string $key): int => \is_int($planned[$key] ?? null) ? $planned[$key] : throw new \TypeError($key);
        $tempo = $planned['tempo'] ?? null;
        $notes = $planned['notes'] ?? null;
        $rir = $planned['targetRir'] ?? null;
        $increment = $planned['incrementKg'] ?? null;

        return Reconstitute::of(PlannedExercise::class, [
            'exerciseId' => new ExerciseId(\is_string($planned['exerciseId'] ?? null) ? $planned['exerciseId'] : ''),
            'sets' => $int('sets'),
            'repRange' => Reconstitute::of(RepRange::class, ['min' => $int('repMin'), 'max' => $int('repMax')]),
            'targetRir' => \is_int($rir) ? $rir : null,
            'restSeconds' => $int('restSeconds'),
            'strategy' => ProgressionStrategy::from(\is_string($planned['strategy'] ?? null) ? $planned['strategy'] : ''),
            'incrementKg' => is_numeric($increment) ? (float) $increment : throw new \TypeError('incrementKg'),
            'warmupSets' => $int('warmupSets'),
            'dropSet' => true === ($planned['dropSet'] ?? false),
            'tempo' => \is_string($tempo) ? Reconstitute::of(Tempo::class, ['value' => $tempo]) : null,
            'notes' => \is_string($notes) ? $notes : null,
        ]);
    }
}
