<?php

declare(strict_types=1);

namespace App\Tests\Api\Shared;

use ApiPlatform\Test\ApiTestCase;

final class OpenApiTest extends ApiTestCase
{
    public function testDeclaredErrorsCarryTheProblemBody(): void
    {
        $spec = static::createClient()->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']])->toArray();
        $responses = self::at($spec, 'paths', '/api/workouts/{id}', 'put', 'responses');

        self::assertSame([200, 400, 404, 409, 422, 429], array_keys($responses), 'what starting a workout can answer, by status');
        $description = self::at($responses, '409')['description'] ?? null;
        self::assertIsString($description);
        self::assertStringContainsString('training.workout_in_progress', $description);
        self::assertSame(['$ref' => '#/components/schemas/Error'], self::at($responses, '409', 'content', 'application/problem+json', 'schema'));
        self::assertSame(['$ref' => '#/components/schemas/Error.jsonld'], self::at($responses, '409', 'content', 'application/ld+json', 'schema'));
        self::assertSame(['$ref' => '#/components/schemas/Workout.jsonld'], self::at($responses, '200', 'content', 'application/ld+json', 'schema'), 'a success is left alone');
    }

    public function testAnErrorDeclaredWithItsOwnBodyKeepsIt(): void
    {
        $spec = static::createClient()->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']])->toArray();

        self::assertSame(
            ['$ref' => '#/components/schemas/Health'],
            self::at($spec, 'paths', '/api/health', 'get', 'responses', '503', 'content', 'application/json', 'schema'),
            'a degraded health check answers with the health report, not a problem',
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function at(array $data, string ...$keys): array
    {
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $data);
            $data = $data[$key];
            self::assertIsArray($data);
        }

        return $data;
    }
}
