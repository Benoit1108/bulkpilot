<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use Symfony\Component\Validator\Constraints as Assert;

final class TimeZoneInput
{
    /** IANA, as the device reports it (`Intl.DateTimeFormat().resolvedOptions().timeZone`). */
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $timeZone = '';
}
