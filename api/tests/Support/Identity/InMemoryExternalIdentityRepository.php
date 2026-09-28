<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\External\ExternalIdentity;
use App\Identity\Domain\External\ExternalIdentityRepository;
use App\Identity\Domain\External\IdentityProvider;

final class InMemoryExternalIdentityRepository implements ExternalIdentityRepository
{
    /** @var list<ExternalIdentity> */
    public array $identities = [];

    public function save(ExternalIdentity $identity): void
    {
        $this->identities[] = $identity;
    }

    public function find(IdentityProvider $provider, string $subject): ?ExternalIdentity
    {
        foreach ($this->identities as $identity) {
            if ($identity->provider() === $provider && $identity->subject() === $subject) {
                return $identity;
            }
        }

        return null;
    }
}
