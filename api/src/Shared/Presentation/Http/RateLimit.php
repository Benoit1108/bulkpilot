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
    public static function consume(RateLimiterFactoryInterface $factory, string $key, int $tokens = 1): void
    {
        $limit = $factory->create(hash('sha256', $key))->consume($tokens);
        if (!$limit->isAccepted() || (0 === $tokens && 0 === $limit->getRemainingTokens())) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many attempts, retry later.');
        }
    }

    /**
     * For a costly write refused often for other reasons (invalid, already in progress): 429 when
     * the bucket is empty, but a token is only taken once the write went through (`consume`).
     */
    public static function ensureAvailable(RateLimiterFactoryInterface $factory, string $key): void
    {
        self::consume($factory, $key, 0);
    }

    /** Takes the token of a write that went through (never refused: it is done). */
    public static function take(RateLimiterFactoryInterface $factory, string $key): void
    {
        $factory->create(hash('sha256', $key))->consume();
    }
}
