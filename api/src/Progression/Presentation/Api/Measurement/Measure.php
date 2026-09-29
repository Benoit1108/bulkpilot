<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Progression\Application\Measurement\MeasureView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * A measure one can take (spec §54): a body part (`name` null, the app names it) or one of the
 * user's own.
 */
#[ApiResource(
    shortName: 'Measure',
    operations: [
        new GetCollection(uriTemplate: '/progression/measures', description: 'The body parts, then the user\'s own measures (not archived).', paginationEnabled: false, provider: MeasureProvider::class),
        new Post(
            uriTemplate: '/progression/measures',
            description: 'Adds a measure of the user\'s own (in centimetres, 20 at most).',
            input: MeasureInput::class,
            openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]),
            processor: MeasureProcessor::class,
        ),
        new Put(
            uriTemplate: '/progression/measures/{key}',
            requirements: ['key' => '[0-9a-f-]{36}'],
            description: 'Renames one of the user\'s own measures.',
            input: MeasureInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: MeasureProcessor::class,
        ),
        new Delete(
            uriTemplate: '/progression/measures/{key}',
            requirements: ['key' => '[0-9a-f-]{36}'],
            description: 'Archives one of the user\'s own measures: it leaves the form, its values stay.',
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: MeasureProcessor::class,
        ),
    ],
)]
final readonly class Measure
{
    public function __construct(
        #[ApiProperty(description: 'A body part (neck, shoulders, chest, left_arm…), or the id of one of the user\'s own.', identifier: true, required: true)]
        public string $key,
        #[ApiProperty(description: 'The user\'s name for their own measure; null for a body part.', required: true)]
        public ?string $name,
        #[ApiProperty(required: true)]
        public bool $custom,
        #[ApiProperty(required: true)]
        public bool $archived,
    ) {
    }

    public static function of(MeasureView $view): self
    {
        return new self($view->key, $view->name, null !== $view->name, $view->archived);
    }
}
