<?php

declare(strict_types=1);

namespace App\Identity\Application\Preferences;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<WidgetView>>
 */
final readonly class GetDashboardLayout implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
