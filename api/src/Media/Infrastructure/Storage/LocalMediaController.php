<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves a file of the local storage through a signed link, as S3 serves a presigned one
 * (ADR-0027). Part of the local storage adapter, as a bucket's HTTP endpoint is part of S3.
 * Development and tests only: the route does not exist in production.
 */
final readonly class LocalMediaController
{
    public function __construct(private LocalMediaStorage $storage, private SignedLocalUrls $urls, private ClockInterface $clock)
    {
    }

    #[Route(SignedLocalUrls::PATH.'{key}', name: 'media_local', requirements: ['key' => LocalMediaStorage::KEY], methods: ['GET'], env: ['dev', 'test'])]
    public function __invoke(string $key, Request $request): BinaryFileResponse
    {
        $download = $request->query->getString('download');
        if (!$this->urls->verifies($key, $request->query->getInt('expires'), $download, $request->query->getString('signature'), $this->clock->now())) {
            throw new NotFoundHttpException();
        }
        $path = $this->storage->path($key);
        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => str_ends_with($key, '.zip') ? 'application/zip' : 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
        $response->headers->set('Cache-Control', 'private, no-store');
        if ('' !== $download) {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $download);
        }

        return $response;
    }
}
