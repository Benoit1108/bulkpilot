<?php

declare(strict_types=1);

namespace App\Audit\Application\Port;

use App\Shared\Domain\Identifier\UserId;

/**
 * The same stable pseudonym for a user, which cannot be turned back into their id without the
 * application's secret (ADR-0030).
 */
interface Pseudonyms
{
    public function of(UserId $userId): string;
}
