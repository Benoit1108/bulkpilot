<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Token\OneTimeToken;
use App\Identity\Domain\Token\OneTimeTokenId;
use App\Identity\Domain\Token\OneTimeTokenRepository;
use App\Identity\Domain\Token\TokenPurpose;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryOneTimeTokenRepository implements OneTimeTokenRepository
{
    /** @var array<string, OneTimeToken> */
    public array $tokens = [];

    public function save(OneTimeToken $token): void
    {
        $this->tokens[$token->id()->value] = $token;
    }

    public function find(OneTimeTokenId $id): ?OneTimeToken
    {
        return $this->tokens[$id->value] ?? null;
    }

    public function removeAllOf(UserId $userId, TokenPurpose $purpose): void
    {
        $this->tokens = array_filter(
            $this->tokens,
            static fn (OneTimeToken $token): bool => !($token->userId()->equals($userId) && $token->purpose() === $purpose),
        );
    }
}
