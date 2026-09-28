<?php

declare(strict_types=1);

namespace App\Identity\Domain\Token;

use App\Identity\Domain\Exception\InvalidToken;
use App\Shared\Domain\Identifier\UserId;

/**
 * Single-use secret sent by email (verification, password reset).
 *
 * The link carries "<id>.<secret>": the id locates the token, only a SHA-256 of the secret is
 * stored, so a database leak does not expose usable links. Secrets are 256-bit random values,
 * which makes a plain hash sufficient (no password-style stretching needed).
 */
class OneTimeToken
{
    private ?\DateTimeImmutable $consumedAt = null;

    /** Optimistic lock (persistence concern): single-use values cannot be spent twice by parallel requests. */
    private int $version = 1;

    private function __construct(
        private readonly OneTimeTokenId $id,
        private readonly TokenPurpose $purpose,
        private readonly UserId $userId,
        private readonly string $secretHash,
        private readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * @param non-empty-string $secret high-entropy random value, returned to the user once
     */
    public static function issue(OneTimeTokenId $id, TokenPurpose $purpose, UserId $userId, string $secret, \DateTimeImmutable $now): self
    {
        return new self($id, $purpose, $userId, self::hash($secret), $now->modify(\sprintf('+%d seconds', $purpose->lifetimeSeconds())));
    }

    /**
     * Burns the token and returns its owner.
     *
     * @throws InvalidToken wrong purpose, wrong secret, expired or already used — indistinguishable on purpose
     */
    public function consume(TokenPurpose $purpose, string $secret, \DateTimeImmutable $now): UserId
    {
        if ($purpose !== $this->purpose
            || null !== $this->consumedAt
            || $now >= $this->expiresAt
            || !hash_equals($this->secretHash, self::hash($secret))) {
            throw InvalidToken::create();
        }

        $this->consumedAt = $now;

        return $this->userId;
    }

    public function id(): OneTimeTokenId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function purpose(): TokenPurpose
    {
        return $this->purpose;
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
