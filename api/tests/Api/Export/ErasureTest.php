<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Export\Application\ExportDomains;
use App\Identity\Application\Deletion\EraseAccount;
use App\Identity\Application\Deletion\EraseDueAccounts;
use App\Media\Application\MediaStorage;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Api\RunsTheWorker;
use App\Tests\Support\Api\TrainingApiTestCase;
use App\Tests\Support\Media\Images;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Every context's personal data (ADR-0030), against the real tables and through the real path (the
 * deletion asked for, the worker's erasure): two users use the app, one is erased; nothing of theirs
 * is left anywhere, and the other keeps everything. A new table must say how it is erased.
 */
final class ErasureTest extends TrainingApiTestCase
{
    use ClockSensitiveTrait;
    use RunsTheWorker;

    /**
     * Tables without an owner column, and why nothing of a user stays in them.
     */
    private const array NOT_OWNED = [
        'doctrine_migration_versions' => 'the schema\'s history',
        'messenger_messages' => 'queued work: an erasure left in `failed` is replayed or dropped by the operator (docs/DEPLOYMENT.md)',
        'sessions' => 'HTTP sessions: those naming the user are deleted with the account (checked below)',
        'system_heartbeats' => 'when the worker and the backups last ran: about the service, nobody\'s data',
        'training_program_versions' => 'owned through training_programs (checked below)',
        'users' => 'the account itself (checked below)',
    ];

    /** Owner tables a day of use does not fill, and why. */
    private const array LEFT_EMPTY = [
        'one_time_tokens' => 'the account is verified by the test helper, no link pending',
        'user_external_identities' => 'no Google sign-in in this test',
        'coach_weekly_reports' => 'no week finished yet',
    ];

    private int $sets = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-09-30 10:00:00');
        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        Images::clean();
        parent::tearDown();
    }

    public function testEveryTableIsOwnedOrKnown(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $unowned = $connection->fetchFirstColumn("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' AND table_name NOT IN (SELECT table_name FROM information_schema.columns WHERE table_schema = 'public' AND column_name IN ('owner_id', 'user_id')) ORDER BY 1");

        self::assertSame(array_keys(self::NOT_OWNED), $unowned, 'a new table without an owner column says here why nothing of a user stays in it');
    }

    public function testAnErasedUserLeavesNothingAndTheOthersKeepEverything(): void
    {
        $alex = $this->register();
        $alexPhoto = $this->useTheApp('0199a0b1-0000-7000-8000-00000000e001');
        $this->runTheWorker();
        $this->useDevice('sam');
        $sam = $this->register('sam@example.com', 'Sam');
        $samPhoto = $this->useTheApp('0199a0b1-0000-7000-8000-00000000e002');
        $this->runTheWorker();
        $storage = self::getContainer()->get(MediaStorage::class);
        $before = $this->rowsOf($alex);
        self::assertSame([], array_keys(array_diff_key(array_filter($before, static fn (int $count): bool => 0 === $count), self::LEFT_EMPTY)), 'the user has rows in every owner table (or the test says why not)');
        self::assertGreaterThan(0, $this->versionsOf($alex));

        $exported = [];
        foreach (self::getContainer()->get(ExportDomains::class)->exportable() as $source) {
            foreach ($source->export(new UserId($alex), new ExportScope()) as $dataset) {
                $exported[$source->domain().'.'.$dataset->name] = \count($dataset->rows);
            }
        }
        $columns = [];
        foreach (self::getContainer()->get(ExportDomains::class)->exportable() as $source) {
            foreach ($source->export(new UserId($alex), new ExportScope()) as $dataset) {
                $columns[$source->domain().'.'.$dataset->name] = $dataset->columns;
            }
        }
        $fixture = __DIR__.'/../../Fixtures/export-columns.json';
        self::assertSame(json_decode((string) file_get_contents($fixture), true), $columns, 'what an export holds is reviewed: a column added or removed changes tests/Fixtures/export-columns.json');
        self::assertGreaterThan(0, $exported['nutrition.journal']);
        self::assertGreaterThan(0, $exported['training.series']);
        self::assertSame(1, $exported['progression.photos']);

        // The real path: the deletion asked for, the delay over, the worker's erasure (every tagged source).
        $this->useDevice('alex');
        $this->signIn();
        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(202);
        self::mockTime('2026-10-08 10:00:00');
        self::getContainer()->get('command.bus')->dispatch(new EraseDueAccounts());
        self::assertGreaterThan(0, $this->runTheWorker());

        self::assertSame([], self::present($this->rowsOf($alex)), 'no row of the erased user is left');
        self::assertSame(0, $this->versionsOf($alex));
        $connection = self::getContainer()->get(Connection::class);
        self::assertSame(0, self::rows($connection->fetchOne('SELECT COUNT(*) FROM sessions WHERE position(CAST(:needle AS BYTEA) IN sess_data) > 0', ['needle' => $alex])), 'no HTTP session names the user');
        $pseudonymised = $connection->fetchFirstColumn("SELECT action FROM audit_events WHERE owner_id IS NULL AND pseudonym IS NOT NULL AND details::text = '{}' ORDER BY created_at");
        self::assertContains('identity.account_erased', $pseudonymised, 'what happened stays, under a pseudonym and without details');
        self::assertContains('identity.signed_in', $pseudonymised);

        $samRows = $this->rowsOf($sam);
        foreach (array_keys(self::present($before)) as $table) {
            self::assertGreaterThan(0, $samRows[$table] ?? 0, \sprintf('the other user keeps their rows in %s', $table));
        }
        $this->assertFileDoesNotExistInStorage($storage, $alexPhoto);
        $kept = tempnam(sys_get_temp_dir(), 'photo');
        self::assertIsString($kept);
        $storage->readTo($samPhoto.'.webp', $kept);
        self::assertGreaterThan(0, filesize($kept));
        unlink($kept);

        // Erasing again (a message redelivered) changes nothing.
        self::getContainer()->get('command.bus')->dispatch(new EraseAccount(new UserId($alex)));
        self::assertSame([], self::present($this->rowsOf($alex)));
    }

    /**
     * Rows of the user in every table that has an owner (`owner_id`, `user_id`, or `id` for users).
     *
     * @return array<string, int>
     */
    private function rowsOf(string $userId): array
    {
        $connection = self::getContainer()->get(Connection::class);
        $counts = [];
        foreach ($connection->fetchAllAssociative("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = 'public' AND column_name IN ('owner_id', 'user_id') ORDER BY table_name") as $column) {
            [$table, $owner] = [$column['table_name'], $column['column_name']];
            self::assertIsString($table);
            self::assertIsString($owner);
            $counts[$table] = self::rows($connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE %s = :user', $table, $owner), ['user' => $userId]));
        }
        $counts['users'] = self::rows($connection->fetchOne('SELECT COUNT(*) FROM users WHERE id = :user', ['user' => $userId]));

        return $counts;
    }

    private function versionsOf(string $userId): int
    {
        return self::rows(self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM training_program_versions v JOIN training_programs p ON p.id = v.program_id WHERE p.owner_id = :user', ['user' => $userId]))
            + self::rows(self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM training_program_versions v WHERE NOT EXISTS (SELECT 1 FROM training_programs p WHERE p.id = v.program_id)'));
    }

    /**
     * A day of use: weigh-ins, a goal and measurements, a photo, a target, meals, a recipe and a favourite,
     * a program and a workout, the Coach, notifications, an export.
     *
     * @return string the photo's media id
     */
    private function useTheApp(string $workoutId): string
    {
        $this->call('PUT', '/api/progression/weigh-ins/2026-09-29', ['weightKg' => 80.5]);
        self::assertResponseStatusCodeSame(200);
        $this->call('PUT', '/api/progression/weight-goal', ['targetKg' => 85, 'milestonesKg' => [82]]);
        self::assertResponseStatusCodeSame(200);
        $this->call('POST', '/api/progression/measures', ['name' => 'Hanches']);
        $this->call('PUT', '/api/progression/measurements/2026-09-29', ['centimetres' => ['waist' => 80]]);
        self::assertResponseStatusCodeSame(200);
        $upload = $this->client->request('POST', '/api/progression/photos', [
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'multipart/form-data'],
            'extra' => ['parameters' => ['day' => '2026-09-29', 'pose' => 'front'], 'files' => ['file' => new UploadedFile(Images::png(40, 60), 'IMG_1.png', 'image/png', null, true)]],
        ]);
        self::assertResponseStatusCodeSame(201);
        $photoUrl = self::string($upload->toArray(), 'url');
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => '2026-09-30', 'energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75]);
        self::assertResponseStatusCodeSame(201);
        $rice = $this->foodId('riz');
        $this->logFood($rice, 150, 'lunch', '2026-09-30');
        $own = self::string($this->call('POST', '/api/foods', ['name' => 'Gainer maison', 'energyKcal' => 400, 'protein' => 20, 'carbohydrate' => 60, 'fat' => 8])->toArray(), 'id');
        $this->call('POST', '/api/recipes', ['name' => 'Riz gainer', 'servings' => 2, 'ingredients' => [['foodId' => $rice, 'grams' => 200], ['foodId' => $own, 'grams' => 50]]]);
        self::assertResponseStatusCodeSame(201);
        $this->call('POST', '/api/nutrition/favorite-meals', ['name' => 'Midi', 'day' => '2026-09-30', 'slot' => 'lunch']);
        self::assertResponseStatusCodeSame(201);
        $this->call('PUT', '/api/nutrition/meal-split', ['breakfast' => 25, 'lunch' => 30, 'snack' => 10, 'gainer' => 15, 'dinner' => 20]);
        self::assertResponseStatusCodeSame(200);
        $bench = $this->exerciseId('developpe couche barre');
        $this->call('POST', '/api/exercises', ['name' => 'Pompes lestées', 'primaryMuscle' => 'chest', 'type' => 'compound', 'equipment' => 'bodyweight', 'measurement' => 'weight', 'repMin' => 8, 'repMax' => 12]);
        self::assertResponseStatusCodeSame(201);
        $this->call('PUT', '/api/exercises/'.$bench.'/note', ['text' => 'Coudes serrés']);
        $this->call('POST', '/api/programs', ['name' => 'Full', 'days' => [['name' => 'A', 'blocks' => [['exercises' => [self::planned($bench)]]]]]]);
        self::assertResponseStatusCodeSame(201);
        $at = static fn (string $when): string => new \DateTimeImmutable('2026-09-29 18:00:00 UTC')->modify($when)->format(\DATE_ATOM);
        $this->call('PUT', '/api/workouts/'.$workoutId, ['startedAt' => $at('+0 minutes')]);
        $this->call('PUT', '/api/workouts/'.$workoutId.'/exercises/0', ['exerciseId' => $bench]);
        $this->call('PUT', \sprintf('/api/workouts/%s/sets/0199a0b1-0000-7000-8000-%012d', $workoutId, ++$this->sets), ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => 60, 'reps' => 8, 'doneAt' => $at('+10 minutes')]);
        $this->call('POST', '/api/workouts/'.$workoutId.'/finish', ['finishedAt' => $at('+45 minutes')]);
        self::assertResponseStatusCodeSame(200);
        // A second session, heavier: a record, so that a notification is made.
        $again = substr($workoutId, 0, -4).'f'.substr($workoutId, -3);
        $started = $this->call('PUT', '/api/workouts/'.$again, ['startedAt' => $at('+14 hours')]);
        self::assertSame(200, $started->getStatusCode(), $started->getContent(false));
        $this->call('PUT', '/api/workouts/'.$again.'/exercises/0', ['exerciseId' => $bench]);
        $this->call('PUT', \sprintf('/api/workouts/%s/sets/0199a0b1-0000-7000-8000-%012d', $again, ++$this->sets), ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => 70, 'reps' => 8, 'doneAt' => $at('+14 hours 10 minutes')]);
        $this->call('POST', '/api/workouts/'.$again.'/finish', ['finishedAt' => $at('+14 hours 45 minutes')]);
        self::assertResponseStatusCodeSame(200);
        $this->call('POST', '/api/coach/analysis', ['today' => '2026-09-30']);
        self::assertResponseStatusCodeSame(200);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => 0.25]);
        $this->call('PUT', '/api/notifications/settings', ['categories' => ['low_calories' => false, 'workout_not_started' => true, 'missing_weigh_in' => true, 'goal_reached' => true, 'record' => true, 'weekly_report' => true], 'quietFrom' => null, 'quietTo' => null]);
        $this->call('POST', '/api/notifications/check');
        self::assertResponseStatusCodeSame(204);
        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'Europe/Paris']);
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(202);

        self::assertSame(1, preg_match('#/api/media/([0-9a-f-]{36})\.webp#', $photoUrl, $matches));

        return $matches[1];
    }

    private function assertFileDoesNotExistInStorage(MediaStorage $storage, string $mediaId): void
    {
        foreach ([$mediaId.'.webp', $mediaId.'-thumbnail.webp'] as $key) {
            $stored = true;
            try {
                $storage->readTo($key, '/dev/null');
            } catch (\RuntimeException) {
                $stored = false;
            }
            self::assertFalse($stored, \sprintf('"%s" is no longer stored', $key));
        }
    }

    /**
     * @param array<string, int> $counts
     *
     * @return array<string, int> the tables with rows
     */
    private static function present(array $counts): array
    {
        return array_filter($counts, static fn (int $count): bool => $count > 0);
    }

    private static function rows(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int) $value;
    }
}
