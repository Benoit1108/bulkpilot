<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Photo;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Progression\Application\Photo\PhotoView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * A progress photo (spec §60, ADR-0027): never public, read through links that expire after a
 * few minutes (ask for the photo again for new ones).
 */
#[ApiResource(
    shortName: 'ProgressPhoto',
    operations: [
        new Get(uriTemplate: '/progression/photos/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'A photo, with fresh links.', provider: ProgressPhotoProvider::class),
        new Post(
            uriTemplate: '/progression/photos',
            inputFormats: ['multipart' => ['multipart/form-data']],
            description: 'Adds a photo of a day: JPEG, PNG or WebP, 15 MB at most, kept re-encoded without its metadata (500 photos at most).',
            deserialize: false,
            validate: false,
            openapi: new Operation(
                responses: ['429' => new Response(Problems::TOO_MANY)],
                requestBody: new RequestBody(content: new \ArrayObject(['multipart/form-data' => ['schema' => [
                    'type' => 'object',
                    'required' => ['file', 'day', 'pose'],
                    'properties' => [
                        'file' => ['type' => 'string', 'format' => 'binary'],
                        'day' => ['type' => 'string', 'format' => 'date'],
                        'pose' => ['type' => 'string', 'enum' => ['front', 'side', 'back', 'other']],
                    ],
                ]]])),
            ),
            processor: ProgressPhotoProcessor::class,
        ),
        new Delete(
            uriTemplate: '/progression/photos/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Deletes the photo for good, its file included.',
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: ProgressPhotoProcessor::class,
        ),
    ],
)]
final readonly class ProgressPhoto
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['front', 'side', 'back', 'other']])]
        public string $pose,
        #[ApiProperty(required: true)]
        public int $width,
        #[ApiProperty(required: true)]
        public int $height,
        #[ApiProperty(description: 'The photo (2048 px a side at most).', required: true)]
        public string $url,
        #[ApiProperty(description: 'A small version (480 px), for lists.', required: true)]
        public string $thumbnailUrl,
        #[ApiProperty(description: 'The photo as a download, under a neutral name.', required: true)]
        public string $downloadUrl,
        #[ApiProperty(description: 'When the links stop working.', required: true)]
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function of(PhotoView $view): self
    {
        return new self($view->id, $view->day->format('Y-m-d'), $view->pose->value, $view->width, $view->height, $view->links->url, $view->links->thumbnailUrl, $view->links->downloadUrl, $view->links->expiresAt);
    }
}
