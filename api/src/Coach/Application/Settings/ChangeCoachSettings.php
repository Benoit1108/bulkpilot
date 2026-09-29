<?php

declare(strict_types=1);

namespace App\Coach\Application\Settings;

use App\Coach\Domain\Settings\Personality;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class ChangeCoachSettings implements Command
{
    public function __construct(public UserId $userId, public Personality $personality, public ?float $paceKgPerWeek)
    {
    }
}
