<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use Symfony\Component\Validator\Constraints as Assert;

final class NotificationSettingsInput
{
    /** @var array<string, bool> every category, on or off */
    #[Assert\NotNull]
    public array $categories = [];

    /** HH:MM in the user's time; both null for no quiet hours (a PUT replaces all the settings). */
    public ?string $quietFrom = null;

    public ?string $quietTo = null;
}
