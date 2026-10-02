<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

/**
 * Lowercase, accent-free form of a name, so that "developpe" finds "Développé couché".
 */
final class SearchKey
{
    public static function of(string $text): string
    {
        // Ligatures (œ, æ) become two letters.
        $text = mb_strtolower($text);
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', false === $ascii ? $text : $ascii));
    }

    /**
     * @return list<string> the words of a query, at most 6
     */
    public static function words(string $query): array
    {
        return \array_slice(array_unique(array_filter(explode(' ', self::of($query)), static fn (string $word): bool => '' !== $word)), 0, 6);
    }
}
