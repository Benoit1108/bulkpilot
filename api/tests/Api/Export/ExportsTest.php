<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Export\Application\ExportArchive;
use App\Export\Application\ExportContents;
use App\Export\Application\GenerateExport;
use App\Export\Application\PurgeExpiredExports;
use App\Export\Application\StartExport;
use App\Export\Domain\ExportId;
use App\Export\Domain\ExportRequest;
use App\Media\Application\MediaStorage;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Api\RunsTheWorker;
use App\Tests\Support\Api\TrainingApiTestCase;
use App\Tests\Support\Media\Images;
use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Exports as the user asks for them and the worker builds them (ADR-0030), on Wednesday 2026-09-30.
 */
final class ExportsTest extends TrainingApiTestCase
{
    use ClockSensitiveTrait;

    use RunsTheWorker;

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

    public function testExportsNeedASignedInUser(): void
    {
        $this->call('GET', '/api/exports');
        self::assertResponseStatusCodeSame(401);
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheDomainsAnExportCanCover(): void
    {
        $this->register();

        $domains = $this->getJson('/api/exports/domains');
        self::assertSame(['identity', 'nutrition', 'training', 'progression', 'coach', 'notification', 'activity'], array_column($domains, 'key'));
        self::assertSame('Entraînement', self::string($domains, '2.title'));
    }

    public function testAnExportIsBuiltByTheWorkerThenDownloadedThroughASignedLink(): void
    {
        $this->register();
        $this->someData();

        $export = $this->call('POST', '/api/exports', [])->toArray();
        self::assertResponseStatusCodeSame(202);
        self::assertSame('queued', $export['status']);
        self::assertFalse($export['downloadable']);
        $id = self::string($export, 'id');

        self::assertSame(1, $this->work());
        $ready = $this->getJson('/api/exports/'.$id);
        self::assertSame('ready', $ready['status']);
        self::assertTrue($ready['downloadable']);
        self::assertSame('2026-10-01T10:00:00+00:00', $ready['expiresAt']);
        self::assertSame($id, self::string($this->getJson('/api/exports'), '0.id'));

        $url = self::string($this->call('POST', '/api/exports/'.$id.'/download')->toArray(), 'url');
        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('#^/api/media/[0-9a-f-]{36}\.zip\?expires=\d+&signature=[0-9a-f]{64}&download=bulkpilot-export-2026-09-30\.zip$#', $url);
        $file = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/zip');
        self::assertStringContainsString('no-store', $file->getHeaders()['cache-control'][0] ?? '');
        $this->client->request('GET', preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $url) ?? '');
        self::assertResponseStatusCodeSame(404, 'a link is only good with its signature');

        $zip = $this->archive($url);
        $json = json_decode((string) $zip->getFromName('donnees.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertSame([79.8, 80.5], array_column(self::array($json, 'data.progression.pesees'), 'Poids (kg)'));
        self::assertSame('alex@example.com', self::string($json, 'data.identity.compte.0.Adresse email'));
        self::assertArrayNotHasKey('password_hash', self::array($json, 'data.identity.compte.0'));
        self::assertStringNotContainsString('$argon', (string) $zip->getFromName('donnees.json'), 'no password hash in an export');
        self::assertStringStartsWith("\u{FEFF}Jour;\"Poids (kg)\";", (string) $zip->getFromName('csv/progression-pesees.csv'));
        self::assertStringStartsWith('%PDF', (string) $zip->getFromName('synthese.pdf'));
        self::assertNotFalse($zip->getFromName('bulkpilot.xlsx'));
        self::assertCount(1, array_filter(self::names($zip), static fn (string $name): bool => 1 === preg_match('#^photos/2026-09-29-face-[0-9a-f]{8}\.webp$#', $name)));
        self::assertStringContainsString('Développé couché', (string) $zip->getFromName('csv/entrainement-series.csv'));
        self::assertFalse($zip->getFromName('csv/entrainement-notes.csv'), 'an empty table has no CSV (it is in the workbook and the summary)');

        $activity = array_column($this->getJson('/api/me/activity'), 'action');
        self::assertContains('export.requested', $activity);
        self::assertContains('export.downloaded', $activity);
    }

    public function testAnExportCoversThePeriodAndTheDomainsAskedFor(): void
    {
        $this->register();
        $this->someData();

        $id = self::string($this->call('POST', '/api/exports', ['from' => '2026-09-29', 'to' => '2026-09-30', 'domains' => ['progression']])->toArray(), 'id');
        self::assertResponseStatusCodeSame(202);
        $this->work();
        $zip = $this->archive(self::string($this->call('POST', '/api/exports/'.$id.'/download')->toArray(), 'url'));
        $json = json_decode((string) $zip->getFromName('donnees.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['progression'], array_keys(self::array($json, 'data')));
        self::assertSame(['2026-09-29'], array_column(self::array($json, 'data.progression.pesees'), 'Jour'), 'the weigh-in of the 20th is before the period');
        self::assertSame(['from' => '2026-09-29', 'to' => '2026-09-30'], self::array($json, 'period'));
    }

    public function testOneExportAtATimeAndAScopeThatMeansSomething(): void
    {
        $this->register();

        $this->call('POST', '/api/exports', ['domains' => ['bank']]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('export.invalid_scope');
        $this->call('POST', '/api/exports', ['from' => '2026-09-30', 'to' => '2026-09-01']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('export.invalid_scope');
        $this->call('POST', '/api/exports', ['from' => '30/09/2026']);
        self::assertResponseStatusCodeSame(422);

        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(202);
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('export.in_progress');
    }

    public function testNoSecretOfTheAccountIsEverExported(): void
    {
        $alex = $this->registerVerified();
        $setup = $this->call('POST', '/api/me/two-factor/setup', ['password' => self::PASSWORD])->toArray();
        $secret = $setup['secret'];
        self::assertIsString($secret);
        \assert('' !== $secret);
        $confirmed = $this->call('POST', '/api/me/two-factor/confirm', ['code' => TOTP::createFromSecret($secret)->at(max(0, Clock::get()->now()->getTimestamp()))])->toArray();
        $this->call('POST', '/api/auth/password-reset-request', ['email' => 'alex@example.com']);
        $connection = self::getContainer()->get(Connection::class);
        $connection->insert('user_external_identities', ['id' => '0199a0b1-0000-7000-8000-0000000000ee', 'user_id' => $alex, 'provider' => 'google', 'subject' => 'google-subject-4242', 'linked_at' => '2026-09-30 09:00:00+00']);
        $id = self::string($this->call('POST', '/api/exports', [])->toArray(), 'id');
        $this->work();
        $zip = $this->archive(self::string($this->call('POST', '/api/exports/'.$id.'/download')->toArray(), 'url'));

        $everything = '';
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $everything .= (string) $zip->getFromIndex($i);
        }
        $text = static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '';
        $secrets = [
            'the password hash' => $text($connection->fetchOne('SELECT password_hash FROM users WHERE id = :id', ['id' => $alex])),
            'the TOTP secret' => $secret,
            'the TOTP secret as stored' => $text($connection->fetchOne('SELECT two_factor_secret FROM users WHERE id = :id', ['id' => $alex])),
            'the provider\'s subject' => 'google-subject-4242',
        ];
        $secrets['the recovery codes as stored'] = $text($connection->fetchOne('SELECT two_factor_recovery_codes FROM users WHERE id = :id', ['id' => $alex]));
        foreach ((array) ($confirmed['recoveryCodes'] ?? []) as $n => $code) {
            $secrets['recovery code '.$n] = $text($code);
        }
        foreach ($connection->fetchFirstColumn('SELECT id FROM user_sessions WHERE user_id = :id', ['id' => $alex]) as $n => $session) {
            $secrets['session '.$n] = $text($session);
        }
        foreach ($connection->fetchFirstColumn('SELECT secret_hash FROM one_time_tokens WHERE user_id = :id', ['id' => $alex]) as $n => $token) {
            $secrets['token '.$n] = $text($token);
        }
        self::assertGreaterThan(5, \count($secrets));
        foreach ($secrets as $what => $value) {
            self::assertNotSame('', $value, $what);
            self::assertStringNotContainsString($value, $everything, \sprintf('%s is not in the export', $what));
        }
        $json = json_decode((string) $zip->getFromName('donnees.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['Fournisseur', 'Liée le'], array_keys(self::array($json, 'data.identity.connexions_externes.0')));
        self::assertSame(['Appareil', 'Connecté le', 'Dernière activité', 'Déconnecté le'], array_keys(self::array($json, 'data.identity.appareils.0')));
    }

    public function testAWorkoutIsExportedOnTheUsersDayNotUtcs(): void
    {
        $this->register();
        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'Europe/Paris']);
        // 00:30 in Paris on the 29th is still the 28th in UTC.
        $this->workout('0199a0b1-0000-7000-8000-00000000e003', '2026-09-28 22:30:00', 60);
        $id = self::string($this->call('POST', '/api/exports', ['from' => '2026-09-29', 'domains' => ['training']])->toArray(), 'id');
        $this->work();
        $zip = $this->archive(self::string($this->call('POST', '/api/exports/'.$id.'/download')->toArray(), 'url'));

        $csv = (string) $zip->getFromName('csv/entrainement-seances.csv');
        self::assertStringContainsString('2026-09-29 00:30', $csv, 'in the user\'s zone, and in the period');
        $json = json_decode((string) $zip->getFromName('donnees.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('2026-09-29T00:30:00+02:00', self::string($json, 'data.training.seances.0.Début'));
        self::assertSame('Europe/Paris', self::string($json, 'timeZone'));
    }

    public function testAFailedExportCanBeAskedForAgain(): void
    {
        $this->register();
        $id = self::string($this->call('POST', '/api/exports', [])->toArray(), 'id');
        self::getContainer()->set(ExportArchive::class, new class implements ExportArchive {
            public function write(string $zipPath, ExportContents $contents, array $datasets, UserId $owner, array $files): void
            {
                throw new \RuntimeException('the disk is full');
            }
        });
        $this->work();

        $failed = $this->getJson('/api/exports/'.$id);
        self::assertSame(['failed', false], [$failed['status'], $failed['downloadable']]);
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(202, 'a failed export is not in progress any more');
    }

    public function testAnExportWhoseWorkerDiedIsFailedLater(): void
    {
        $this->register();
        $id = self::string($this->call('POST', '/api/exports', [])->toArray(), 'id');
        // The worker takes it, then dies (out of memory): no one marks it.
        self::getContainer()->get('command.bus')->dispatch(new StartExport(new ExportId($id)));
        self::assertSame('processing', $this->getJson('/api/exports/'.$id)['status']);
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(409);

        self::mockTime('2026-09-30 10:31:00');
        self::getContainer()->get('command.bus')->dispatch(new PurgeExpiredExports());
        self::assertSame('failed', $this->getJson('/api/exports/'.$id)['status']);
        // Its message delivered again finds it taken and stops.
        $this->work();
        self::assertSame('failed', $this->getJson('/api/exports/'.$id)['status']);
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(202);
    }

    public function testARefusedRequestTakesNoneOfTheDaysExports(): void
    {
        $this->register();
        for ($i = 0; $i < 6; ++$i) {
            $this->call('POST', '/api/exports', ['domains' => ['bank']]);
            self::assertResponseStatusCodeSame(422);
        }
        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(202);
    }

    public function testTheDelaysAreTheServers(): void
    {
        $this->register();

        self::assertSame(
            ['deletionDelayDays' => 7, 'exportAvailableHours' => ExportRequest::KEPT_HOURS, 'activityRetentionDays' => 365, 'reauthenticationMinutes' => 10],
            array_intersect_key($this->getJson('/api/me/data-policy'), array_flip(['deletionDelayDays', 'exportAvailableHours', 'activityRetentionDays', 'reauthenticationMinutes'])),
        );
        $domains = $this->getJson('/api/exports/domains');
        self::assertFalse(self::array($domains, '0')['periodFiltered'], 'the account is always complete');
        self::assertTrue(self::array($domains, '1')['periodFiltered']);
    }

    public function testAFewExportsADay(): void
    {
        $this->register();
        for ($i = 0; $i < 5; ++$i) {
            $this->call('POST', '/api/exports', []);
            self::assertResponseStatusCodeSame(202);
            $this->work();
        }

        $this->call('POST', '/api/exports', []);
        self::assertResponseStatusCodeSame(429);
    }

    public function testAnExportIsTheUsersOwn(): void
    {
        $this->register();
        $id = self::string($this->call('POST', '/api/exports', [])->toArray(), 'id');
        $this->work();

        $this->useDevice('sam');
        $this->register('sam@example.com', 'Sam');
        $this->call('GET', '/api/exports/'.$id);
        self::assertResponseStatusCodeSame(404);
        $this->call('POST', '/api/exports/'.$id.'/download');
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->getJson('/api/exports'));
        $this->call('GET', '/api/exports/not-an-id');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnArchiveIsKeptADayThenDeleted(): void
    {
        $this->register();
        $id = self::string($this->call('POST', '/api/exports', [])->toArray(), 'id');
        $this->work();
        $url = self::string($this->call('POST', '/api/exports/'.$id.'/download')->toArray(), 'url');
        $key = $this->key($url);

        self::mockTime('2026-10-01 10:00:01');
        self::assertSame('expired', $this->getJson('/api/exports/'.$id)['status'], 'past its day, before the purge');
        $this->call('POST', '/api/exports/'.$id.'/download');
        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('export.not_ready');

        self::getContainer()->get('command.bus')->dispatch(new PurgeExpiredExports());
        // The archive goes once the expiry committed, in the worker (retried until it is done).
        $this->runTheWorker();
        self::assertSame('expired', $this->getJson('/api/exports/'.$id)['status']);
        $this->expectException(\RuntimeException::class);
        self::getContainer()->get(MediaStorage::class)->readTo($key, '/dev/null');
    }

    /**
     * A weigh-in in and one out of the last days, a photo, a meal and a workout.
     */
    private function someData(): void
    {
        $this->call('PUT', '/api/progression/weigh-ins/2026-09-20', ['weightKg' => 79.8]);
        $this->call('PUT', '/api/progression/weigh-ins/2026-09-29', ['weightKg' => 80.5]);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/progression/photos', [
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'multipart/form-data'],
            'extra' => ['parameters' => ['day' => '2026-09-29', 'pose' => 'front'], 'files' => ['file' => new UploadedFile(Images::png(40, 60), 'IMG_1.png', 'image/png', null, true)]],
        ]);
        self::assertResponseStatusCodeSame(201);
        $this->logFood($this->foodId('riz'), 150, 'lunch', '2026-09-30');
        $this->workout('0199a0b1-0000-7000-8000-00000000e001', '2026-09-29 18:00:00', 60);
    }

    private function workout(string $id, string $startedAt, float $load): void
    {
        $at = static fn (string $when): string => new \DateTimeImmutable($startedAt.' UTC')->modify($when)->format(\DATE_ATOM);
        $this->call('PUT', '/api/workouts/'.$id, ['startedAt' => $at('+0 minutes')]);
        $this->call('PUT', '/api/workouts/'.$id.'/exercises/0', ['exerciseId' => $this->exerciseId('developpe couche barre')]);
        $this->call('PUT', \sprintf('/api/workouts/%s/sets/0199a0b1-0000-7000-8000-%012d', $id, ++$this->sets), ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => $load, 'reps' => 8, 'doneAt' => $at('+10 minutes')]);
        $this->call('POST', '/api/workouts/'.$id.'/finish', ['finishedAt' => $at('+45 minutes')]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Builds the queued exports, as the worker would.
     *
     * @return int the exports built
     */
    private function work(): int
    {
        return $this->runTheWorker(GenerateExport::class);
    }

    private function key(string $url): string
    {
        self::assertSame(1, preg_match('#^/api/media/([^?]+)\?#', $url, $matches));

        return rawurldecode($matches[1]);
    }

    private function archive(string $url): \ZipArchive
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'export-test-');
        self::getContainer()->get(MediaStorage::class)->readTo($this->key($url), $path);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));

        return $zip;
    }

    /**
     * @return list<string>
     */
    private static function names(\ZipArchive $zip): array
    {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string) $zip->getNameIndex($i);
        }

        return $names;
    }
}
