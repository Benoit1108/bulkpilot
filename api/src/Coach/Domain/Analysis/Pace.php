<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

/**
 * How the weight moves against the pace aimed at. The band around the pace is half of it, and
 * at least 0.2 kg a week, so that a few hundred grams of water do not make a verdict.
 */
enum Pace: string
{
    case OnTrack = 'on_track';
    /** The weight goes up less (or down more) than aimed at. */
    case Below = 'below';
    /** The weight goes up more (or down less) than aimed at. */
    case Above = 'above';
    case Unknown = 'unknown';

    public const float MIN_BAND_KG = 0.2;

    public static function of(?float $aimedKgPerWeek, ?float $kgPerWeek): self
    {
        if (null === $aimedKgPerWeek || null === $kgPerWeek) {
            return self::Unknown;
        }
        $band = max(abs($aimedKgPerWeek) / 2, self::MIN_BAND_KG);

        return match (true) {
            $kgPerWeek < $aimedKgPerWeek - $band => self::Below,
            $kgPerWeek > $aimedKgPerWeek + $band => self::Above,
            default => self::OnTrack,
        };
    }
}
