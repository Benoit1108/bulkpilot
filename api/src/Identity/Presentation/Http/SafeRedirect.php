<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

/**
 * Where to send the browser after signing in: only a path of this app. Mirrors
 * pwa/app/features/identity/access.ts (same rule, same test table).
 *
 * Rejected: other origins, protocol-relative "//x" and "/\x", and any control or space character
 * (browsers strip tabs and newlines from URLs, turning "/\t/evil" into "//evil").
 */
final class SafeRedirect
{
    public static function path(string $candidate): string
    {
        return 1 === preg_match('#^/(?![/\\\\])[^\x00-\x20\x7f\\\\]*$#', $candidate) ? $candidate : '/';
    }
}
