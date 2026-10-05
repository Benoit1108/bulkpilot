<?php

declare(strict_types=1);

namespace App\Tests\Api\System;

use App\System\Presentation\Api\ClientConfig\ClientConfigCaching;
use App\Tests\Support\Api\IdentityApiTestCase;

/**
 * What the PWA reads before anyone signs in (docs/MONITORING.md): nothing secret, the same for all.
 */
final class ClientConfigTest extends IdentityApiTestCase
{
    public function testAnyoneReadsItAndItIsCachedBriefly(): void
    {
        $response = $this->call('GET', '/api/client-config');

        self::assertResponseIsSuccessful();
        // No DSN in the tests: the PWA reports no error and loads nothing.
        self::assertJsonEquals(['environment' => 'dev', 'errorReportingDsn' => null, 'release' => 'dev']);
        $cacheControl = $response->getHeaders()['cache-control'][0] ?? '';
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age='.ClientConfigCaching::MAX_AGE, $cacheControl);
    }

    public function testASignedInReadIsStillPublicAndSetsNoCookie(): void
    {
        $this->registerVerified();
        $this->signIn();

        $response = $this->call('GET', '/api/client-config');

        self::assertResponseIsSuccessful();
        $headers = $response->getHeaders();
        self::assertArrayNotHasKey('set-cookie', $headers, 'a shared cache must never keep someone\'s session');
        self::assertStringContainsString('public', $headers['cache-control'][0] ?? '');
    }
}
