<?php

declare(strict_types=1);

namespace App\Tests\Api\Performance;

use App\Tests\Support\Api\DemoAccountTestCase;

/**
 * SQL statements per request on two months of a demo account (spec §119: no N+1): each budget
 * is far below the number of days, workouts or entries, so a query per row fails it at once.
 * Raise a budget only with a reason (docs/TESTING.md).
 */
final class QueryBudgetTest extends DemoAccountTestCase
{
    /**
     * One test for every request measured: seeding the account is the costly part (the test
     * isolation rolls it back after each test), so it is done once.
     */
    public function testEveryRequestStaysWithinItsBudget(): void
    {
        $over = [];
        foreach ($this->readings() as $name => [$uri, $budget]) {
            $statements = array_column($this->measure('GET', $uri), 'sql');
            if (\count($statements) > $budget) {
                $over[] = \sprintf("%s (%s): %d statements over a budget of %d:\n  %s", $name, $uri, \count($statements), $budget, implode("\n  ", $statements));
            }
        }
        // The Coach reads 14 days of meals, 4 weeks of weigh-ins and the training history.
        $over = [...$over, ...$this->over('coach analysis', 'POST', '/api/coach/analysis', ['today' => $this->today, 'again' => true], 17)];
        // The notification checks read the day of every context.
        $over = [...$over, ...$this->over('notification checks', 'POST', '/api/notifications/check', [], 21)];

        // Recording a set: the session, the workout, its update and the version check (the offline sync relies on it).
        $workout = '0199a0b1-0000-7000-8000-00000000b001';
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->call('PUT', '/api/workouts/'.$workout, ['startedAt' => $now->format(\DATE_ATOM)]);
        self::assertResponseIsSuccessful();
        $exercises = $this->call('GET', '/api/exercises')->toArray();
        $exercise = \is_array($exercises[0] ?? null) ? ($exercises[0]['id'] ?? null) : null;
        self::assertIsString($exercise);
        $this->call('PUT', '/api/workouts/'.$workout.'/exercises/0', ['exerciseId' => $exercise]);
        self::assertResponseIsSuccessful();
        $over = [...$over, ...$this->over('recording a set', 'PUT', '/api/workouts/'.$workout.'/sets/0199a0b1-0000-7000-8000-00000000b101', ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => 60, 'reps' => 8, 'doneAt' => $now->format(\DATE_ATOM)], 6)];

        self::assertSame([], $over, implode("\n\n", $over));
    }

    /**
     * @param array<string, mixed> $json
     *
     * @return list<string>
     */
    private function over(string $name, string $method, string $uri, array $json, int $budget): array
    {
        $statements = array_column($this->measure($method, $uri, $json), 'sql');

        return \count($statements) > $budget ? [\sprintf("%s: %d statements over a budget of %d:\n  %s", $name, \count($statements), $budget, implode("\n  ", $statements))] : [];
    }
}
