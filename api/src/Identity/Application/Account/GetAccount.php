<?php

declare(strict_types=1);

namespace App\Identity\Application\Account;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<AccountView>
 */
final readonly class GetAccount implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
