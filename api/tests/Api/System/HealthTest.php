<?php

declare(strict_types=1);

namespace App\Tests\Api\System;

use ApiPlatform\Test\ApiTestCase;
use App\System\Application\Health\HealthCheck;
use App\System\Infrastructure\Health\DatabaseHealthCheck;

final class HealthTest extends ApiTestCase
{
    public function testHealthReportsEveryDependency(): void
    {
        static::createClient()->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);

        self::assertResponseIsSuccessful();
        self::assertJsonEquals(['status' => 'ok', 'checks' => ['database' => 'up']]);
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
        self::assertJsonEquals(['status' => 'degraded', 'checks' => ['database' => 'down']]);
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
