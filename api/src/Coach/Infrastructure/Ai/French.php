<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Ai;

/**
 * Figures written the French way (spec: UI copy in French): "2 340 kcal", "+0,4 kg", "le 12 septembre".
 * Spaces before units are non-breaking.
 */
final class French
{
    private const string NBSP = "\u{00A0}";
    private const string MINUS = "\u{2212}";
    private const array MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public static function number(float $value, int $decimals = 0): string
    {
        $rounded = round($value, $decimals);

        return ($rounded < 0 ? self::MINUS : '').number_format(abs($rounded), $decimals, ',', self::NBSP);
    }

    public static function kcal(float $value): string
    {
        return self::number($value).self::NBSP.'kcal';
    }

    public static function grams(float $value): string
    {
        return self::number($value).self::NBSP.'g';
    }

    public static function kg(float $value): string
    {
        return self::number($value, 1).self::NBSP.'kg';
    }

    /** With its sign: "+0,4 kg", "−0,2 kg", "0,0 kg". */
    public static function signedKg(float $value): string
    {
        return (round($value, 1) > 0 ? '+' : '').self::kg($value);
    }

    /** A pace as it was set, to the hundredth when it has one: "+0,25 kg", "+0,5 kg". */
    public static function pace(float $value): string
    {
        $decimals = round($value, 2) === round($value, 1) ? 1 : 2;

        return (round($value, $decimals) > 0 ? '+' : '').self::number($value, $decimals).self::NBSP.'kg';
    }

    public static function signedKcal(int $value): string
    {
        return ($value > 0 ? '+' : '').self::kcal($value);
    }

    public static function percent(float $value): string
    {
        return self::number($value).self::NBSP.'%';
    }

    public static function day(string $day): string
    {
        $date = new \DateTimeImmutable($day);

        return \sprintf('le %d %s', (int) $date->format('j'), self::MONTHS[(int) $date->format('n') - 1]);
    }

    /** "0 séance", "1 séance", "3 séances": in French, 0 and 1 take the singular. */
    public static function count(int $count, string $one, string $many): string
    {
        return \sprintf('%d %s', $count, abs($count) < 2 ? $one : $many);
    }

    /** Non-breaking spaces before « : ; ! ? % », as French typography wants them. */
    public static function typeset(string $text): string
    {
        return (string) preg_replace('/ ([:;!?%])/u', self::NBSP.'$1', $text);
    }
}
