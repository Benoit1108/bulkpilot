<?php

declare(strict_types=1);

namespace App\Identity\Application\Token;

use App\Identity\Application\Port\SecretGenerator;
use App\Identity\Domain\Exception\InvalidToken;
use App\Identity\Domain\Token\OneTimeToken;
use App\Identity\Domain\Token\OneTimeTokenId;
use App\Identity\Domain\Token\OneTimeTokenRepository;
use App\Identity\Domain\Token\TokenPurpose;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * The single place where emailed one-time links are issued and redeemed.
 * The value put in the link is "<token id>.<secret>"; only the secret's hash is stored.
 */
final readonly class OneTimeLinks
{
    public function __construct(
        private OneTimeTokenRepository $tokens,
        private SecretGenerator $secrets,
        private UuidGenerator $uuids,
    ) {
    }

    /**
     * Previous links of the same purpose stop working.
     *
     * @return non-empty-string the value to put in the link
     */
    public function issue(TokenPurpose $purpose, UserId $userId, \DateTimeImmutable $now): string
    {
        $this->tokens->removeAllOf($userId, $purpose);

        $id = new OneTimeTokenId($this->uuids->generate());
        $secret = $this->secrets->generate();
        $this->tokens->save(OneTimeToken::issue($id, $purpose, $userId, $secret, $now));

        return $id->value.'.'.$secret;
    }

    /**
     * Burns the link and returns its owner.
     *
     * @throws InvalidToken malformed, unknown, wrong purpose, expired or already used — indistinguishable
     */
    public function redeem(TokenPurpose $purpose, string $link, \DateTimeImmutable $now): UserId
    {
        [$id, $secret] = explode('.', $link, 2) + ['', ''];
        if ('' === $secret || 1 !== preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            throw InvalidToken::create();
        }

        $token = $this->tokens->find(new OneTimeTokenId($id)) ?? throw InvalidToken::create();
        $userId = $token->consume($purpose, $secret, $now);
        $this->tokens->save($token);

        return $userId;
    }
}
