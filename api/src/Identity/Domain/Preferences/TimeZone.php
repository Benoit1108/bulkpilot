<?php

declare(strict_types=1);

namespace App\Identity\Domain\Preferences;

use App\Identity\Domain\Exception\InvalidTimeZone;

/**
 * The IANA time zone of the user's devices: scheduled work (notifications, the nightly Coach
 * analysis) follows the user's own day and hours with it. Former names still reported by some
 * devices ("America/Montreal", "Asia/Calcutta") are accepted, exactly as spelled.
 */
final readonly class TimeZone
{
    /** Until a device says otherwise. */
    public const string DEFAULT = 'Europe/Paris';

    public string $name;

    public function __construct(string $name)
    {
        if (!\in_array($name, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw InvalidTimeZone::named($name);
        }
        $this->name = $name;
    }

    /**
     * A zone read back from storage: one the time zone database no longer knows (a name retired
     * by an update) gives the default rather than stopping scheduled work.
     */
    public static function orDefault(string $name): self
    {
        try {
            return new self($name);
        } catch (InvalidTimeZone) {
            return new self(self::DEFAULT);
        }
    }
}
