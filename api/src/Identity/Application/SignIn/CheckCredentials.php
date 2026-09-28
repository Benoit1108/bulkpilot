<?php

declare(strict_types=1);

namespace App\Identity\Application\SignIn;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<UserId>
 */
final readonly class CheckCredentials implements Query
{
    public function __construct(public string $email, public string $password)
    {
    }
}
