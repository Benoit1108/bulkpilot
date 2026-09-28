<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Identity\Domain\External\ExternalIdentityRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Identifier\UserId;

final readonly class FindExternalIdentityOwnerHandler implements QueryHandler
{
    public function __construct(private ExternalIdentityRepository $identities)
    {
    }

    public function __invoke(FindExternalIdentityOwner $query): UserId
    {
        return $this->identities->find($query->provider, $query->subject)?->userId()
            ?? throw ExternalSignInFailed::because('no account is linked to this identity');
    }
}
