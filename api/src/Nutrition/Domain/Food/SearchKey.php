<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

/**
 * Lowercase, accent-free form of a name, so that "oeuf", "Œuf" and "OEUF" match (and "pates", "pâtes").
 */
final class SearchKey
{
    /** Shorter words match nearly everything (and escape the trigram index). */
    public const int MIN_WORD_LENGTH = 2;
    public const int MAX_WORDS = 6;

    /**
     * The words a search really filters on.
     *
     * @return list<string>
     */
    public static function words(string $key): array
    {
        return \array_slice(array_unique(array_filter(explode(' ', $key), static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH)), 0, self::MAX_WORDS);
    }

    public static function of(string ...$parts): string
    {
        // Empty parts leave only spaces, collapsed below; ligatures (œ, æ, ß) become two letters.
        $text = mb_strtolower(implode(' ', $parts));
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);

        return trim((string) preg_replace('/\s+/', ' ', false === $ascii ? $text : $ascii));
    }
}
