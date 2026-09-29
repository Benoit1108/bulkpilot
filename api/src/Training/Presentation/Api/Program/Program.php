<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;
use App\Training\Application\Program\ProgramView;

/**
 * A training plan: days of exercises (a block of several is a superset). Changing its days
 * records a new version; past workouts stay tied to the version they followed.
 */
#[ApiResource(
    shortName: 'Program',
    operations: [
        new GetCollection(uriTemplate: '/programs', description: 'The user\'s programs, the active one first, each with its current version.', paginationEnabled: false, provider: ProgramProvider::class),
        new Get(
            uriTemplate: '/programs/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'A program, one of its versions spelled out (the current one by default), and the list of its versions.',
            provider: ProgramProvider::class,
            parameters: [
                'version' => new QueryParameter(description: 'Number of the version to spell out.', schema: ['type' => 'integer', 'minimum' => 1]),
            ],
        ),
        new Post(uriTemplate: '/programs', description: 'Creates a program; it becomes the one followed when none is.', input: ProgramInput::class, openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]), processor: ProgramProcessor::class),
        new Put(uriTemplate: '/programs/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Renames a program and, when its days changed, records them as its next version.', input: ProgramInput::class, read: false, openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]), processor: ProgramProcessor::class),
        new Post(
            uriTemplate: '/programs/{id}/activate',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 200,
            description: 'Makes it the program the user follows; the previous one is no longer active.',
            input: false,
            read: false,
            openapi: new Operation(responses: ['404' => new Response(Problems::NOT_FOUND), '409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: ProgramProcessor::class,
            name: 'program_activate',
        ),
        new Post(
            uriTemplate: '/programs/{id}/deactivate',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 200,
            description: 'The user follows no program for now.',
            input: false,
            read: false,
            openapi: new Operation(responses: ['404' => new Response(Problems::NOT_FOUND), '409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: ProgramProcessor::class,
            name: 'program_deactivate',
        ),
        new Delete(uriTemplate: '/programs/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Archives a program; past workouts keep the version they followed.', read: false, openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]), processor: ProgramProcessor::class),
    ],
)]
final readonly class Program
{
    /**
     * @param list<ProgramDay>            $days
     * @param list<ProgramVersionSummary> $versions the latest first (empty in the list of programs)
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true)]
        public bool $active,
        /** Number of the program's current version. */
        #[ApiProperty(required: true)]
        public int $currentVersion,
        /** Number of the version these days are from. */
        #[ApiProperty(required: true)]
        public int $version,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $versionCreatedAt,
        #[ApiProperty(required: true)]
        public array $days,
        #[ApiProperty(required: true)]
        public array $versions,
    ) {
    }

    public static function fromView(ProgramView $view): self
    {
        return new self(
            $view->id,
            $view->name,
            $view->active,
            $view->currentVersion,
            $view->versionNumber,
            $view->versionCreatedAt,
            array_map(ProgramDay::fromView(...), $view->days),
            array_map(static fn (array $version): ProgramVersionSummary => new ProgramVersionSummary($version['number'], $version['createdAt']), $view->versions),
        );
    }
}
