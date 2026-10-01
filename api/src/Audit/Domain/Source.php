<?php

declare(strict_types=1);

namespace App\Audit\Domain;

/**
 * Who did it: the user, the Coach once the user accepted (spec §10: "source: coach"), or the
 * application on its own (a scheduled erasure).
 */
final class Source
{
    public const string USER = 'user';
    public const string COACH = 'coach';
    public const string SYSTEM = 'system';
    public const array ALL = [self::USER, self::COACH, self::SYSTEM];

    public static function isKnown(string $source): bool
    {
        return \in_array($source, self::ALL, true);
    }
}
