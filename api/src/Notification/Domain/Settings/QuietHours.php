<?php

declare(strict_types=1);

namespace App\Notification\Domain\Settings;

use App\Notification\Domain\Exception\InvalidNotificationSettings;

/**
 * When nothing is sent (spec §76), in the user's time: from one time to another, across midnight
 * when it ends earlier than it starts. No quiet hours at all is no QuietHours (null).
 */
final readonly class QuietHours
{
    private const string PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(public string $from, public string $to)
    {
        if (1 !== preg_match(self::PATTERN, $from) || 1 !== preg_match(self::PATTERN, $to)) {
            throw InvalidNotificationSettings::because('quiet hours are HH:MM times');
        }
        if ($from === $to) {
            throw InvalidNotificationSettings::because('quiet hours end at another time than they start (send none for no quiet hours)');
        }
    }

    public static function default(): self
    {
        return new self('23:00', '08:00');
    }

    public function covers(\DateTimeImmutable $localNow): bool
    {
        $time = $localNow->format('H:i');

        return match (true) {
            $this->from < $this->to => $time >= $this->from && $time < $this->to,
            default => $time >= $this->from || $time < $this->to,
        };
    }
}
