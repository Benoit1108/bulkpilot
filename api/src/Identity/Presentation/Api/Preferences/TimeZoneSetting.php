<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The time zone of the user's devices, which scheduled notifications and the nightly Coach
 * analysis follow (ADR-0029). Read with the account (`GET /api/me`).
 */
#[ApiResource(
    shortName: 'TimeZone',
    operations: [
        new Put(
            uriTemplate: '/me/time-zone',
            status: 204,
            description: 'Sets the IANA time zone of the user\'s devices (the PWA sends its own when it differs), kept in the preferences apart from the account: it never conflicts with an account change; 422 `identity.invalid_time_zone` for an unknown zone.',
            input: TimeZoneInput::class,
            output: false,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: TimeZoneProcessor::class,
        ),
    ],
)]
final readonly class TimeZoneSetting
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $timeZone,
    ) {
    }
}
