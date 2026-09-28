<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Consumes one token of a limiter (config/packages/framework.yaml → rate_limiter): 429 with
 * Retry-After once the bucket is empty. Keys are hashed, so no email or id ends up in the store.
 */
final class RateLimit
{
    public static function consume(RateLimiterFactoryInterface $factory, string $key): void
    {
        $limit = $factory->create(hash('sha256', $key))->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many attempts, retry later.');
        }
    }
}
