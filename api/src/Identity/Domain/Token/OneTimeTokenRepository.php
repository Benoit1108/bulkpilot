<?php

declare(strict_types=1);

namespace App\Identity\Domain\Token;

use App\Shared\Domain\Identifier\UserId;

interface OneTimeTokenRepository
{
    public function save(OneTimeToken $token): void;

    public function find(OneTimeTokenId $id): ?OneTimeToken;

    /**
     * Invalidates the user's previous tokens of this purpose (only the latest link works).
     */
    public function removeAllOf(UserId $userId, TokenPurpose $purpose): void;
}
