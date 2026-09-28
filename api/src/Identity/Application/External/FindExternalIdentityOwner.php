<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Identity\Domain\External\IdentityProvider;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<UserId>
 */
final readonly class FindExternalIdentityOwner implements Query
{
    public function __construct(public IdentityProvider $provider, public string $subject)
    {
    }
}
