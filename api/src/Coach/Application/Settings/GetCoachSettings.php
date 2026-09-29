<?php

declare(strict_types=1);

namespace App\Coach\Application\Settings;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<CoachSettingsView>
 */
final readonly class GetCoachSettings implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
