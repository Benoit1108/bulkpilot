<?php

declare(strict_types=1);

namespace App\System\Application\ClientConfig;

/**
 * What the PWA needs to know of its deployment at start, decided per environment rather than
 * baked into the build: nothing secret (a browser DSN is public by design).
 */
final readonly class ClientConfig
{
    public function __construct(public ?string $errorReportingDsn, public string $environment, public string $release)
    {
    }
}
