<?php

declare(strict_types=1);

namespace App\System\Presentation\Api\ClientConfig;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\System\Application\ClientConfig\ClientConfig as Config;

/**
 * The PWA's deployment settings (docs/MONITORING.md): public, read at start, cached briefly.
 */
#[ApiResource(
    shortName: 'ClientConfig',
    operations: [
        new Get(
            uriTemplate: '/client-config',
            description: 'Settings of this deployment the PWA reads at start (error reporting); nothing secret.',
            provider: ClientConfigProvider::class,
        ),
    ],
)]
final readonly class ClientConfig
{
    public function __construct(
        #[ApiProperty(identifier: true, description: 'production, staging or dev.', required: true)]
        public string $environment,
        #[ApiProperty(description: 'Where the PWA reports its errors (a Sentry-compatible DSN); null to report none.', required: true)]
        public ?string $errorReportingDsn,
        #[ApiProperty(description: 'The version deployed (git commit).', required: true)]
        public string $release,
    ) {
    }

    public static function of(Config $config): self
    {
        return new self($config->environment, $config->errorReportingDsn, $config->release);
    }
}
