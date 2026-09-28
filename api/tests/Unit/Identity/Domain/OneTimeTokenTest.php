<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\InvalidToken;
use App\Identity\Domain\Token\OneTimeToken;
use App\Identity\Domain\Token\OneTimeTokenId;
use App\Identity\Domain\Token\TokenPurpose;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

final class OneTimeTokenTest extends TestCase
{
    private \DateTimeImmutable $issuedAt;

    protected function setUp(): void
    {
        $this->issuedAt = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testTheRightSecretReturnsTheOwnerOnce(): void
    {
        $token = $this->issue(TokenPurpose::PasswordReset);

        self::assertSame('0199a0b1-7c3e-7d4a-9b2f-000000000001', $token->consume(TokenPurpose::PasswordReset, 's3cret', $this->issuedAt)->value);

        $this->expectException(InvalidToken::class);
        $token->consume(TokenPurpose::PasswordReset, 's3cret', $this->issuedAt);
    }

    public function testAWrongSecretIsRejected(): void
    {
        $this->expectException(InvalidToken::class);

        $this->issue(TokenPurpose::PasswordReset)->consume(TokenPurpose::PasswordReset, 'guess', $this->issuedAt);
    }

    public function testATokenCannotServeAnotherPurpose(): void
    {
        $this->expectException(InvalidToken::class);

        $this->issue(TokenPurpose::EmailVerification)->consume(TokenPurpose::PasswordReset, 's3cret', $this->issuedAt);
    }

    public function testPasswordResetLinksExpireAfterOneHour(): void
    {
        $token = $this->issue(TokenPurpose::PasswordReset);
        $token->consume(TokenPurpose::PasswordReset, 's3cret', $this->issuedAt->modify('+3599 seconds'));

        $this->expectException(InvalidToken::class);
        $this->issue(TokenPurpose::PasswordReset)->consume(TokenPurpose::PasswordReset, 's3cret', $this->issuedAt->modify('+3600 seconds'));
    }

    public function testVerificationLinksLastAWeek(): void
    {
        self::assertSame(7 * 24 * 3600, TokenPurpose::EmailVerification->lifetimeSeconds());

        $this->expectException(InvalidToken::class);
        $this->issue(TokenPurpose::EmailVerification)->consume(TokenPurpose::EmailVerification, 's3cret', $this->issuedAt->modify('+7 days'));
    }

    private function issue(TokenPurpose $purpose): OneTimeToken
    {
        return OneTimeToken::issue(
            new OneTimeTokenId('0199a0b1-7c3e-7d4a-9b2f-00000000000b'),
            $purpose,
            new UserId('0199a0b1-7c3e-7d4a-9b2f-000000000001'),
            's3cret',
            $this->issuedAt,
        );
    }
}
