<?php

declare(strict_types=1);

namespace App\Tests\Api\Security;

use ApiPlatform\Test\ApiTestCase;
use App\Shared\Presentation\Http\ApiContentSecurityPolicy;

/**
 * The API answers data: opened as a document, it may load nothing and be framed nowhere. The
 * rest of the headers is set by Caddy and checked on the production image (e2e/security.spec.ts).
 */
final class ApiHeadersTest extends ApiTestCase
{
    public function testDataMayLoadNothing(): void
    {
        $client = static::createClient();

        // With debug on, Symfony drops the policy of error responses (for its error pages), and the
        // profiler appends its nonces: production sends it alone, errors included (e2e/security.spec.ts).
        $response = $client->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);

        self::assertStringStartsWith(ApiContentSecurityPolicy::POLICY, $response->getHeaders()['content-security-policy'][0] ?? '');
    }

    public function testThePagesOfTheApiDocsAreLeftAlone(): void
    {
        static::createClient()->request('GET', '/api/docs', ['headers' => ['Accept' => 'text/html']]);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Content-Security-Policy');
    }
}
