<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use Symfony\Component\Validator\Constraints as Assert;

final class DecisionInput
{
    #[Assert\NotNull]
    #[Assert\Choice(choices: ['accept', 'reject', 'postpone'])]
    public ?string $decision = null;

    /** The device's today (YYYY-MM-DD): an accepted change starts the day after. */
    public ?string $today = null;
}
