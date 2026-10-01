<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

/**
 * What an export can cover.
 */
#[ApiResource(
    shortName: 'ExportDomain',
    operations: [
        new GetCollection(
            uriTemplate: '/exports/domains',
            description: 'The domains an export can pick, in the order they come.',
            paginationEnabled: false,
            provider: ExportDomainProvider::class,
        ),
    ],
)]
final readonly class ExportDomain
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $key,
        #[ApiProperty(required: true)]
        public string $title,
        #[ApiProperty(description: 'Whether the export\'s period narrows it (the account and settings are always complete).', required: true)]
        public bool $periodFiltered,
    ) {
    }
}
