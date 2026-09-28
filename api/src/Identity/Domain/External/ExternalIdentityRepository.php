<?php

declare(strict_types=1);

namespace App\Identity\Domain\External;

interface ExternalIdentityRepository
{
    public function save(ExternalIdentity $identity): void;

    public function find(IdentityProvider $provider, string $subject): ?ExternalIdentity;
}
