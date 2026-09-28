<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Identity\Domain\Token\OneTimeToken;
use App\Identity\Domain\Token\OneTimeTokenId;
use App\Identity\Domain\Token\OneTimeTokenRepository;
use App\Identity\Domain\Token\TokenPurpose;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\HashedPassword;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\Sex;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private const string USER = '0199a0b1-7c3e-7d4a-9b2f-000000000001';

    private EntityManagerInterface $entityManager;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testAUserRoundTripsWithItsValueObjects(): void
    {
        $users = self::getContainer()->get(UserRepository::class);
        $user = User::register(new UserId(self::USER), new EmailAddress('alex@example.com'), new HashedPassword('$argon2id$hash'), new Profile('Alex', $this->now, new \DateTimeImmutable('2000-01-15'), Sex::Male, 178), $this->now);
        $user->verifyEmail($this->now);
        $users->save($user);
        $this->flushAndClear();

        $loaded = $users->get(new UserId(self::USER));
        self::assertNotSame($user, $loaded);
        self::assertSame('alex@example.com', $loaded->email()->value);
        self::assertSame('$argon2id$hash', $loaded->password()?->value);
        self::assertTrue($loaded->isEmailVerified());
        self::assertSame(Sex::Male, $loaded->profile()->sex);
        self::assertSame(178, $loaded->profile()->heightCm);
        self::assertSame('2000-01-15', $loaded->profile()->birthDate?->format('Y-m-d'));
        self::assertSame([], $loaded->pullDomainEvents(), 'events are not persisted');
        self::assertTrue($loaded->id()->equals($users->findByEmail(new EmailAddress('ALEX@example.com'))?->id() ?? new UserId(self::USER)));
        self::assertNull($users->findByEmail(new EmailAddress('nobody@example.com')));
    }

    public function testActiveSessionsAreListedMostRecentFirst(): void
    {
        $sessions = self::getContainer()->get(UserSessionRepository::class);
        $old = UserSession::open(new UserSessionId('0199a0b1-7c3e-7d4a-9b2f-00000000000a'), new UserId(self::USER), 'Old', $this->now);
        $recent = UserSession::open(new UserSessionId('0199a0b1-7c3e-7d4a-9b2f-00000000000b'), new UserId(self::USER), 'Recent', $this->now->modify('+1 hour'));
        $revoked = UserSession::open(new UserSessionId('0199a0b1-7c3e-7d4a-9b2f-00000000000c'), new UserId(self::USER), 'Revoked', $this->now->modify('+2 hours'));
        $revoked->revoke($this->now);
        $other = UserSession::open(new UserSessionId('0199a0b1-7c3e-7d4a-9b2f-00000000000d'), new UserId('0199a0b1-7c3e-7d4a-9b2f-000000000002'), 'Other', $this->now);
        foreach ([$old, $recent, $revoked, $other] as $session) {
            $sessions->save($session);
        }
        $this->flushAndClear();

        $labels = array_map(static fn (UserSession $s): string => $s->deviceLabel(), $sessions->activeOf(new UserId(self::USER), $this->now->modify('+2 hours')));

        self::assertSame(['Recent', 'Old'], $labels);
    }

    public function testOnlyTheUsersTokensOfThatPurposeAreRemoved(): void
    {
        $tokens = self::getContainer()->get(OneTimeTokenRepository::class);
        $user = new UserId(self::USER);
        $reset = OneTimeToken::issue(new OneTimeTokenId('0199a0b1-7c3e-7d4a-9b2f-0000000000a1'), TokenPurpose::PasswordReset, $user, 'a', $this->now);
        $verification = OneTimeToken::issue(new OneTimeTokenId('0199a0b1-7c3e-7d4a-9b2f-0000000000a2'), TokenPurpose::EmailVerification, $user, 'b', $this->now);
        $othersReset = OneTimeToken::issue(new OneTimeTokenId('0199a0b1-7c3e-7d4a-9b2f-0000000000a3'), TokenPurpose::PasswordReset, new UserId('0199a0b1-7c3e-7d4a-9b2f-000000000002'), 'c', $this->now);
        foreach ([$reset, $verification, $othersReset] as $token) {
            $tokens->save($token);
        }
        $this->flushAndClear();

        $tokens->removeAllOf($user, TokenPurpose::PasswordReset);

        self::assertNull($tokens->find($reset->id()));
        self::assertNotNull($tokens->find($othersReset->id()));
        $stored = $tokens->find($verification->id());
        self::assertNotNull($stored);
        self::assertSame($user->value, $stored->consume(TokenPurpose::EmailVerification, 'b', $this->now)->value);
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
