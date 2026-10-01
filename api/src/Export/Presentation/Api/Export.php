<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Export\Application\ExportView;
use App\Export\Application\ListExports;
use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportStatus;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * An export of the user's data (spec §104, ADR-0030): made by the worker, then downloadable for a day.
 */
#[ApiResource(
    shortName: 'Export',
    operations: [
        new GetCollection(
            uriTemplate: '/exports',
            description: 'The last '.ListExports::LIMIT.' exports, the latest first.',
            paginationEnabled: false,
            provider: ExportProvider::class,
        ),
        new Get(
            uriTemplate: '/exports/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            provider: ExportProvider::class,
            openapi: new Operation(responses: ['404' => new Response(Problems::NOT_FOUND)]),
        ),
        new Post(
            uriTemplate: '/exports',
            status: 202,
            description: 'Asks for an export (a period and domains, all by default): a ZIP with JSON, CSV, Excel, a PDF summary and the photos, kept '.ExportRequest::KEPT_HOURS.' h once ready.',
            input: ExportInput::class,
            read: false,
            openapi: new Operation(responses: [
                '409' => new Response('An export is already being made (`export.in_progress`), or: '.Problems::CONCURRENT),
                '422' => new Response('Unknown domain or period ending before it starts (`export.invalid_scope`), or invalid input.'),
                '429' => new Response('Too many exports today: send it again after `Retry-After` seconds.'),
            ]),
            processor: ExportProcessor::class,
            name: 'export_request',
        ),
    ],
)]
final readonly class Export
{
    /**
     * @param list<string> $domains
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['queued', 'processing', 'ready', 'failed', 'expired']])]
        public string $status,
        #[ApiProperty(description: 'First day covered; null from the start.', required: true, schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?string $from,
        #[ApiProperty(description: 'Last day covered; null up to now.', required: true, schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?string $to,
        #[ApiProperty(description: 'The domains covered; empty for all of them.', required: true)]
        public array $domains,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $requestedAt,
        #[ApiProperty(required: true)]
        public ?\DateTimeImmutable $readyAt,
        #[ApiProperty(description: 'When the archive is deleted.', required: true)]
        public ?\DateTimeImmutable $expiresAt,
        #[ApiProperty(description: 'Ready and not yet deleted: POST …/download gives a link.', required: true)]
        public bool $downloadable,
    ) {
    }

    public static function of(ExportView $view): self
    {
        return new self(
            $view->id,
            $view->status->value,
            $view->from?->format('Y-m-d'),
            $view->to?->format('Y-m-d'),
            $view->domains,
            $view->requestedAt,
            $view->readyAt,
            $view->expiresAt,
            ExportStatus::Ready === $view->status,
        );
    }
}
