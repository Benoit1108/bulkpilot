<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Notification\Application\Settings\NotificationSettingsView;
use App\Notification\Domain\Notification\Category;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * Which notifications the user wants (spec §75) and when to be left alone (spec §76), in their time.
 */
#[ApiResource(
    shortName: 'NotificationSettings',
    operations: [
        new Get(uriTemplate: '/notifications/settings', provider: NotificationSettingsProvider::class),
        new Put(
            uriTemplate: '/notifications/settings',
            input: NotificationSettingsInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: NotificationSettingsProcessor::class,
        ),
    ],
)]
final readonly class NotificationSettings
{
    /**
     * @param array<string, bool> $categories
     */
    public function __construct(
        #[ApiProperty(description: 'Every category, on or off.', required: true, schema: [
            'type' => 'object',
            'required' => Category::VALUES,
            // Spelled out for the generated TypeScript types; a test keeps them equal to Category::VALUES.
            'properties' => [
                'low_calories' => ['type' => 'boolean'],
                'workout_not_started' => ['type' => 'boolean'],
                'missing_weigh_in' => ['type' => 'boolean'],
                'goal_reached' => ['type' => 'boolean'],
                'record' => ['type' => 'boolean'],
                'weekly_report' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ])]
        public array $categories,
        #[ApiProperty(description: 'Quiet from (HH:MM, the user\'s time); null with quietTo: no quiet hours.', required: true, schema: ['type' => ['string', 'null'], 'pattern' => '^\d{2}:\d{2}$'])]
        public ?string $quietFrom,
        #[ApiProperty(description: 'Quiet until (HH:MM), another time than quietFrom; null with quietFrom.', required: true, schema: ['type' => ['string', 'null'], 'pattern' => '^\d{2}:\d{2}$'])]
        public ?string $quietTo,
    ) {
    }

    public static function of(NotificationSettingsView $view): self
    {
        return new self($view->categories, $view->quietFrom, $view->quietTo);
    }
}
