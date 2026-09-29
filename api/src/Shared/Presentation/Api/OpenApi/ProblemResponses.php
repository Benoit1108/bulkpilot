<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Error responses declared on an operation with a description only (`new Response('…')`) get the
 * problem body every error has, like the ones API Platform declares itself; responses are listed by status.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class ProblemResponses implements OpenApiFactoryInterface
{
    private const array MEDIA_TYPES = [
        'application/ld+json' => '#/components/schemas/Error.jsonld',
        'application/problem+json' => '#/components/schemas/Error',
        'application/json' => '#/components/schemas/Error',
    ];

    public function __construct(#[AutowireDecorated] private OpenApiFactoryInterface $decorated)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();
        foreach ($paths->getPaths() as $path => $item) {
            if ($item instanceof PathItem) {
                $paths->addPath((string) $path, $this->withProblems($item));
            }
        }

        return $openApi;
    }

    private function withProblems(PathItem $item): PathItem
    {
        return $item
            ->withGet($this->operation($item->getGet()))
            ->withPut($this->operation($item->getPut()))
            ->withPost($this->operation($item->getPost()))
            ->withPatch($this->operation($item->getPatch()))
            ->withDelete($this->operation($item->getDelete()));
    }

    private function operation(?Operation $operation): ?Operation
    {
        if (null === $operation) {
            return null;
        }
        $responses = [];
        foreach ($operation->getResponses() ?? [] as $status => $response) {
            $responses[$status] = $response instanceof Response && (int) $status >= 400 && null === $response->getContent()
                ? $response->withContent(self::content())
                : $response;
        }

        // By status, as a reader looks them up (declared ones would otherwise come first).
        ksort($responses);

        return $operation->withResponses($responses);
    }

    /**
     * @return \ArrayObject<string, array{schema: array{'$ref': string}}>
     */
    private static function content(): \ArrayObject
    {
        $content = [];
        foreach (self::MEDIA_TYPES as $type => $schema) {
            $content[$type] = ['schema' => ['$ref' => $schema]];
        }

        return new \ArrayObject($content);
    }
}
