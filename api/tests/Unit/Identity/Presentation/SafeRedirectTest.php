<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Presentation;

use App\Identity\Presentation\Http\SafeRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Same table as pwa/test/unit/access.test.ts.
 */
final class SafeRedirectTest extends TestCase
{
    #[DataProvider('cases')]
    public function testOnlyInAppPathsAreFollowed(string $candidate, string $expected): void
    {
        self::assertSame($expected, SafeRedirect::path($candidate));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cases(): iterable
    {
        yield 'app path' => ['/training', '/training'];
        yield 'app path with query' => ['/training?x=1&y=%2F', '/training?x=1&y=%2F'];
        yield 'root' => ['/', '/'];
        yield 'empty' => ['', '/'];
        yield 'absolute url' => ['https://evil.example', '/'];
        yield 'protocol relative' => ['//evil.example', '/'];
        yield 'backslash' => ['/\\evil.example', '/'];
        yield 'tab trick' => ["/\t/evil.example", '/'];
        yield 'newline trick' => ["/\n/evil.example", '/'];
        yield 'space' => ['/ /evil.example', '/'];
        yield 'no leading slash' => ['training', '/'];
        yield 'javascript' => ['javascript:alert(1)', '/'];
    }
}
