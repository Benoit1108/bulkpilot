<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Photo;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Progression\Application\Photo\PhotoSessionView;

/**
 * The photos of a day, with that day's weight and measurements, or the latest before it (spec §61).
 */
#[ApiResource(
    shortName: 'PhotoSession',
    operations: [
        new GetCollection(
            uriTemplate: '/progression/photo-sessions',
            description: 'The photo sessions, the most recent first; links valid a few minutes.',
            paginationEnabled: false,
            provider: PhotoSessionProvider::class,
        ),
    ],
)]
final readonly class PhotoSession
{
    /**
     * @param array{day: string, weightKg: float}|null                                            $weight
     * @param array{day: string, values: list<array{key: string, name: ?string, cm: float}>}|null $measurements
     * @param list<ProgressPhoto>                                                                 $photos
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(description: 'The weigh-in of the day, or the latest before it.', required: true, schema: ['type' => ['object', 'null'], 'properties' => ['day' => ['type' => 'string', 'format' => 'date'], 'weightKg' => ['type' => 'number']], 'required' => ['day', 'weightKg']])]
        public ?array $weight,
        #[ApiProperty(description: 'The measurements of the day, or the latest before it; `name` null for a body part.', required: true, schema: ['type' => ['object', 'null'], 'properties' => ['day' => ['type' => 'string', 'format' => 'date'], 'values' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['key' => ['type' => 'string'], 'name' => ['type' => ['string', 'null']], 'cm' => ['type' => 'number']], 'required' => ['key', 'name', 'cm']]]], 'required' => ['day', 'values']])]
        public ?array $measurements,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public array $photos,
    ) {
    }

    public static function of(PhotoSessionView $view): self
    {
        return new self(
            $view->day->format('Y-m-d'),
            null === $view->weight ? null : ['day' => $view->weight->day->format('Y-m-d'), 'weightKg' => $view->weight->kg],
            null === $view->measurements ? null : [
                'day' => $view->measurements->day->format('Y-m-d'),
                'values' => array_map(static fn (string $key, float $cm): array => ['key' => $key, 'name' => $view->measureNames[$key] ?? null, 'cm' => $cm], array_keys($view->measurements->centimetres), array_values($view->measurements->centimetres)),
            ],
            array_map(ProgressPhoto::of(...), $view->photos),
        );
    }
}
