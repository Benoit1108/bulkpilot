<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Export\Application\GetExportLink;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * A signed link to a ready export's archive, asked for when the user downloads it (traced).
 */
#[ApiResource(
    shortName: 'ExportDownload',
    operations: [
        new Post(
            uriTemplate: '/exports/{id}/download',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 200,
            description: 'A link valid '.GetExportLink::LINK_SECONDS.' s to download the archive; the download is recorded in the account activity.',
            input: false,
            read: false,
            openapi: new Operation(responses: [
                '404' => new Response(Problems::NOT_FOUND),
                '409' => new Response('Not ready, or no longer kept (`export.not_ready`).'),
                '429' => new Response(Problems::TOO_MANY),
            ]),
            processor: ExportDownloadProcessor::class,
        ),
    ],
)]
final readonly class ExportDownload
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public string $url,
    ) {
    }
}
