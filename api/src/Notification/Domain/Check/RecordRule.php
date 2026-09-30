<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;

/**
 * Records beaten in a workout just finished (spec §75: PR), once per workout.
 */
final readonly class RecordRule implements Rule
{
    public function evaluate(Situation $situation): array
    {
        return array_map(
            static fn (RecordSession $session): Draft => new Draft(
                Category::Record,
                'record:'.$session->workoutId,
                1 === $session->records ? 'Nouveau record' : \sprintf('%d nouveaux records', $session->records),
                1 === $session->records ? 'Tu as battu un record pendant ta séance.' : \sprintf('Tu as battu %d records pendant ta séance.', $session->records),
                '/training/seances/'.$session->workoutId,
            ),
            $situation->recordSessions,
        );
    }
}
