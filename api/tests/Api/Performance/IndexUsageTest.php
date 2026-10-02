<?php

declare(strict_types=1);

namespace App\Tests\Api\Performance;

use App\Tests\Support\Api\DemoAccountTestCase;
use Doctrine\DBAL\Connection;

/**
 * The reads of the main screens, the writes of a session and the analyses run when the app opens
 * find their rows through an index (spec §119), on several accounts' history: each statement they
 * send is explained with sequential scans disabled, so a plan that still scans a table that grows
 * with use means no index fits it, and an index walked without a condition (to sort, say) is no
 * better.
 */
final class IndexUsageTest extends DemoAccountTestCase
{
    protected const int OTHER_ACCOUNTS = 2;

    /** Tables that grow with use (every day, every account); the others stay small (settings, the shared library). */
    private const array GROWING = [
        'journal_entries', 'progression_weigh_ins', 'progression_measurement_days', 'progression_photos', 'progression_custom_measures', 'workouts',
        'nutrition_targets', 'coach_recommendations', 'coach_weekly_reports', 'notifications', 'notification_checks', 'audit_events',
        'user_sessions', 'export_requests', 'media_files', 'foods', 'recipes', 'favorite_meals', 'exercise_notes', 'exercises',
        'training_programs', 'training_program_versions', 'one_time_tokens', 'messenger_messages', 'users',
    ];

    public function testTheMainReadsUseAnIndex(): void
    {
        $statements = [];
        $requests = [
            ...array_map(static fn (array $reading): array => ['GET', $reading[0], null], array_values($this->readings())),
            // The analyses the app runs on opening: they read every context.
            ['POST', '/api/coach/analysis', ['today' => $this->today, 'again' => true]],
            ['POST', '/api/notifications/check', []],
            // A session's writes: what the offline sync sends again and again.
            ['PUT', '/api/workouts/'.self::WORKOUT, ['startedAt' => $this->now()]],
            ['PUT', '/api/workouts/'.self::WORKOUT.'/exercises/0', ['exerciseId' => $this->anExercise()]],
            ['PUT', '/api/workouts/'.self::WORKOUT.'/sets/'.self::SET, ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => 60, 'reps' => 8, 'doneAt' => $this->now()]],
            ['DELETE', '/api/workouts/'.self::WORKOUT.'/sets/'.self::SET, null],
            ['POST', '/api/notifications/read-all', []],
        ];
        foreach ($requests as [$method, $uri, $json]) {
            foreach ($this->measure($method, $uri, $json) as $statement) {
                // EXPLAIN without ANALYZE runs nothing: writes are explained too.
                if (1 === preg_match('/^\s*(SELECT|UPDATE|DELETE)\b/i', $statement['sql'])) {
                    $statements[$statement['sql']] = $statement['params'];
                }
            }
        }
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('SET LOCAL enable_seqscan = off');

        $scans = [];
        foreach ($statements as $sql => $params) {
            $plan = $connection->fetchOne('EXPLAIN (FORMAT JSON) '.$sql, self::positional($params));
            self::assertIsString($plan);
            foreach (self::scanned(json_decode($plan, true, flags: \JSON_THROW_ON_ERROR)) as [$table, $how]) {
                if (\in_array($table, self::GROWING, true)) {
                    $scans[] = \sprintf("%s %s by:\n  %s", $table, $how, $sql);
                }
            }
        }

        self::assertSame([], $scans, implode("\n\n", $scans));
        self::assertGreaterThan(15, \count($statements), 'The reads sent their statements.');
    }

    /**
     * The values as bound by the driver (positions from 1, or names), as DBAL takes them.
     *
     * @param array<int|string, mixed> $params
     *
     * @return array<int<0, max>|string, mixed>
     */
    private static function positional(array $params): array
    {
        $named = [];
        foreach ($params as $key => $value) {
            if (\is_string($key)) {
                $named[$key] = $value;
            }
        }
        if ([] !== $named) {
            return $named;
        }
        ksort($params);

        return array_values($params);
    }

    /**
     * The tables a plan reads whole: sequentially, or through an index walked without a condition.
     *
     * @return list<array{string, string}> table, how
     */
    private static function scanned(mixed $node): array
    {
        if (!\is_array($node)) {
            return [];
        }
        $type = $node['Node Type'] ?? null;
        $table = $node['Relation Name'] ?? null;
        $tables = [];
        if (\is_string($table) && 'Seq Scan' === $type) {
            $tables[] = [$table, 'scanned'];
        }
        if (\is_string($table) && \in_array($type, ['Index Scan', 'Index Only Scan', 'Bitmap Heap Scan'], true) && !self::hasCondition($node)) {
            $tables[] = [$table, 'walked whole through an index'];
        }
        foreach ($node as $child) {
            if (\is_array($child)) {
                $tables = [...$tables, ...self::scanned($child)];
            }
        }

        return $tables;
    }

    /**
     * An index condition on this node (a bitmap heap scan rechecks the condition of its index scans).
     *
     * @param array<mixed> $node
     */
    private static function hasCondition(array $node): bool
    {
        return isset($node['Index Cond']) || isset($node['Recheck Cond']);
    }

    private const string WORKOUT = '0199a0b1-0000-7000-8000-00000000c001';
    private const string SET = '0199a0b1-0000-7000-8000-00000000c101';

    private function now(): string
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->format(\DATE_ATOM);
    }

    private function anExercise(): string
    {
        $exercises = $this->call('GET', '/api/exercises')->toArray();
        $id = \is_array($exercises[0] ?? null) ? ($exercises[0]['id'] ?? null) : null;
        self::assertIsString($id);

        return $id;
    }
}
