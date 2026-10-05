<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Monitoring;

/**
 * Masks what could identify someone or open something in a text sent to error reporting: e-mail
 * addresses, long tokens (signatures, keys, one-time links) and the values a database error quotes
 * (PostgreSQL's « DETAIL: Key (…)=(…) », « Failing row contains (…) », query parameters) — a weight
 * or a note is health data. UUIDs stay: they are opaque ids, and the only way to find the record an
 * error is about.
 */
final class Redactor
{
    private const string EMAIL = '/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u';
    /** 32 characters or more of a token alphabet (base64url, hex), a UUID aside; paths and dots break it. */
    private const string TOKEN = '/(?<![\w-])(?![0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?![\w-]))[A-Za-z0-9_\-+]{32,}={0,2}/u';

    private const string URL_QUERY = '#(\bhttps?://[^\s?\#]+|(?<![\w/])/[\w\-./]*)[?\#][^\s]*#u';

    /** What PostgreSQL and Doctrine quote of the data in an error: the columns stay, the values go. */
    private const array DATABASE_VALUES = [
        '/(Key \([^)]*\))=\((?:[^()]|\([^()]*\))*\)/u' => '$1=([values])',
        '/Failing row contains \((?:[^()]|\([^()]*\))*\)/u' => 'Failing row contains ([values])',
        '/ with params \[.*?\](?=:|$)/su' => ' with params [values]',
    ];

    public static function text(string $text): string
    {
        $text = (string) preg_replace(array_keys(self::DATABASE_VALUES), array_values(self::DATABASE_VALUES), $text);

        return (string) preg_replace([self::EMAIL, self::TOKEN], ['[email]', '[token]'], $text);
    }

    /** The text redacted, and every URL in it without its query and fragment. */
    public static function withoutQueries(string $text): string
    {
        return self::text((string) preg_replace(self::URL_QUERY, '$1', $text));
    }

    /**
     * Every string inside, keys kept.
     *
     * @template T of array<array-key, mixed>
     *
     * @param T $data
     *
     * @return T
     */
    public static function values(array $data): array
    {
        array_walk_recursive($data, static function (mixed &$value): void {
            if (\is_string($value)) {
                $value = self::text($value);
            }
        });

        return $data;
    }
}
