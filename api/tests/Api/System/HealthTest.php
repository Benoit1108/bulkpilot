<?php

declare(strict_types=1);

namespace App\Tests\Api\System;

use ApiPlatform\Test\ApiTestCase;
use App\System\Application\Health\HealthCheck;
use App\System\Application\Monitoring\Beats;
use App\System\Domain\Monitoring\Beat;
use App\System\Infrastructure\Health\DatabaseHealthCheck;

final class HealthTest extends ApiTestCase
{
    public function testHealthReportsEveryDependency(): void
    {
        static::createClient()->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);

        self::assertResponseIsSuccessful();
        self::assertJsonEquals(['status' => 'ok', 'checks' => ['database' => 'up'], 'monitors' => null]);
    }

    public function testTheMonitoringScopeAlsoWatchesTheWorkerTheBackupsAndTheDisk(): void
    {
        $client = static::createClient();
        $request = static fn () => $client->request('GET', '/api/health?scope=monitoring', ['headers' => ['Accept' => 'application/json']]);

        // A fresh database: the worker never beat. Readiness is not concerned (the container stays up).
        $request();
        self::assertResponseStatusCodeSame(503);
        self::assertJsonEquals(['status' => 'degraded', 'checks' => ['database' => 'up'], 'monitors' => ['worker' => 'unknown', 'backup' => 'disabled', 'backup_verify' => 'disabled', 'disk' => 'ok']]);
        $client->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);
        self::assertResponseIsSuccessful();

        self::getContainer()->get(Beats::class)->record(Beat::Scheduler, new \DateTimeImmutable());
        $request();
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'ok', 'monitors' => ['worker' => 'ok']]);
    }

    public function testADownDependencyAnswers503WithTheDetailedReport(): void
    {
        $client = static::createClient();
        self::getContainer()->set(DatabaseHealthCheck::class, new readonly class implements HealthCheck {
            public function name(): string
            {
                return 'database';
            }

            public function isHealthy(): bool
            {
                return false;
            }
        });

        $client->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);

        self::assertResponseStatusCodeSame(503);
        self::assertJsonEquals(['status' => 'degraded', 'checks' => ['database' => 'down'], 'monitors' => null]);
    }

    public function testOpenApiSpecificationIsPublished(): void
    {
        $response = static::createClient()->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']]);

        self::assertResponseIsSuccessful();
        $spec = $response->toArray();
        self::assertArrayHasKey('paths', $spec);
        self::assertIsArray($spec['paths']);
        self::assertArrayHasKey('/api/health', $spec['paths']);
    }

    public function testUnknownApiRoutesAnswerProblemJson(): void
    {
        static::createClient()->request('GET', '/api/does-not-exist', ['headers' => ['Accept' => 'application/json']]);

        self::assertResponseStatusCodeSame(404);
    }
}
