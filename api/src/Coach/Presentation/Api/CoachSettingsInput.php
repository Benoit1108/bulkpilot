<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use App\Coach\Domain\Settings\CoachSettings;
use Symfony\Component\Validator\Constraints as Assert;

final class CoachSettingsInput
{
    #[Assert\NotNull]
    #[Assert\Choice(choices: ['direct', 'encouraging', 'neutral'])]
    public ?string $personality = null;

    #[Assert\Range(min: -CoachSettings::MAX_PACE_KG, max: CoachSettings::MAX_PACE_KG)]
    public ?float $paceKgPerWeek = null;
}
