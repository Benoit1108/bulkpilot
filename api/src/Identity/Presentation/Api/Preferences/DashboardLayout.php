<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Identity\Application\Preferences\WidgetView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The home dashboard's widgets, in order, and which are shown (spec §13, §82), kept with the
 * account so that every device shows the same dashboard.
 */
#[ApiResource(
    shortName: 'DashboardLayout',
    operations: [
        new Get(uriTemplate: '/me/dashboard', description: 'Every widget, in the user\'s order (the default one until arranged).', provider: DashboardLayoutProvider::class),
        new Put(
            uriTemplate: '/me/dashboard',
            description: 'Arranges the dashboard: every widget exactly once, in order.',
            input: DashboardLayoutInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: DashboardLayoutProcessor::class,
        ),
    ],
)]
final readonly class DashboardLayout
{
    /**
     * @param list<WidgetOutput> $widgets
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public array $widgets,
    ) {
    }

    /**
     * @param list<WidgetView> $views
     */
    public static function of(array $views): self
    {
        return new self(array_map(WidgetOutput::of(...), $views));
    }
}
