<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\EmailAlreadyVerified;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\EmailVerified;
use App\Identity\Domain\User\HashedPassword;
use App\Identity\Domain\User\PasswordChanged;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRegistered;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testRegistrationStartsUnverifiedAndIsAnnounced(): void
    {
        $user = $this->user();

        self::assertFalse($user->isEmailVerified());
        self::assertSame('alex@example.com', $user->email()->value);
        self::assertSame($this->now->format(\DATE_ATOM), $user->registeredAt()->format(\DATE_ATOM));
        $events = $user->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserRegistered::class, $events[0]);
        self::assertSame('alex@example.com', $events[0]->email->value);
    }

    public function testEmailIsVerifiedOnce(): void
    {
        $user = $this->user();
        $user->pullDomainEvents();

        $user->verifyEmail($this->now);

        self::assertTrue($user->isEmailVerified());
        self::assertInstanceOf(EmailVerified::class, $user->pullDomainEvents()[0]);
        $this->expectException(EmailAlreadyVerified::class);
        $user->verifyEmail($this->now);
    }

    public function testChangingThePasswordIsAnnounced(): void
    {
        $user = $this->user();
        $user->pullDomainEvents();

        $user->changePassword(new HashedPassword('new-hash'), $this->now);

        self::assertSame('new-hash', $user->password()?->value);
        self::assertInstanceOf(PasswordChanged::class, $user->pullDomainEvents()[0]);
    }

    public function testTheProfileCanBeReplaced(): void
    {
        $user = $this->user();

        $user->updateProfile(new Profile('Sam', $this->now, null, null, 170));

        self::assertSame('Sam', $user->profile()->firstName);
        self::assertSame(170, $user->profile()->heightCm);
    }

    private function user(): User
    {
        return User::register(
            new UserId('0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b'),
            new EmailAddress('alex@example.com'),
            new HashedPassword('hash'),
            new Profile('Alex', $this->now),
            $this->now,
        );
    }
}
