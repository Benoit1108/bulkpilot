<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api\OpenApi;

/**
 * Descriptions of the error responses operations declare (`openapi: new Operation(responses: …)`);
 * ProblemResponses adds their body.
 */
final class Problems
{
    public const string NOT_FOUND = 'Not found, or not the user\'s.';
    public const string CONCURRENT = 'Changed by another request at the same time (`shared.concurrent_update`): send it again.';
    public const string TOO_MANY = 'Too many writes: send it again after `Retry-After` seconds.';
    public const string INVALID_QUERY = 'A query parameter is malformed.';

    private function __construct()
    {
    }
}
