<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Coach\Application\Settings\CoachSettingsView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * How the Coach speaks (spec §131) and the pace it judges the weight against.
 */
#[ApiResource(
    shortName: 'CoachSettings',
    operations: [
        new Get(uriTemplate: '/coach/settings', provider: CoachSettingsProvider::class),
        new Put(
            uriTemplate: '/coach/settings',
            description: 'Applies from the next analysis; what was already said keeps its wording.',
            input: CoachSettingsInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: CoachSettingsProcessor::class,
        ),
    ],
)]
final readonly class CoachSettings
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'enum' => ['direct', 'encouraging', 'neutral']])]
        public string $personality,
        #[ApiProperty(description: 'Kg a week, signed; null to follow the one the nutrition target was estimated for.', required: true)]
        public ?float $paceKgPerWeek,
    ) {
    }

    public static function of(CoachSettingsView $settings): self
    {
        return new self($settings->personality->value, $settings->paceKgPerWeek);
    }
}
