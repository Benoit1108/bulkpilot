<?php

declare(strict_types=1);

namespace App\Shared\Application\Accounts;

use App\Shared\Domain\Identifier\UserId;

/**
 * An account and the IANA time zone of its devices: "today" and "20:00" are its own.
 */
final readonly class AccountClock
{
    public function __construct(public UserId $userId, public string $timeZone)
    {
    }

    public function now(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->setTimezone(new \DateTimeZone($this->timeZone));
    }
}
