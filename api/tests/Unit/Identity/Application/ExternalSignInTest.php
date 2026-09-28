<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\External\ExternalProfile;
use App\Identity\Application\External\ExternalSignInFailed;
use App\Identity\Application\External\FindExternalIdentityOwner;
use App\Identity\Application\External\FindExternalIdentityOwnerHandler;
use App\Identity\Application\External\SignInWithExternalIdentity;
use App\Identity\Application\External\SignInWithExternalIdentityHandler;
use App\Identity\Application\Port\RegistrationPolicy;
use App\Identity\Domain\Exception\ExternalAccountConflict;
use App\Identity\Domain\Exception\RegistrationClosed;
use App\Identity\Domain\External\ExternalIdentityLinked;
use App\Identity\Domain\External\IdentityProvider;
use App\Identity\Domain\User\UserRegistered;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Identity\IdentityTestCase;
use App\Tests\Support\Identity\InMemoryExternalIdentityRepository;

final class ExternalSignInTest extends IdentityTestCase
{
    private const string NEW_ID = '0199a0b1-7c3e-7d4a-9b2f-00000000abcd';

    private InMemoryExternalIdentityRepository $identities;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identities = new InMemoryExternalIdentityRepository();
    }

    public function testANewPersonGetsAVerifiedAccountWithoutPassword(): void
    {
        $this->signIn($this->profile('camille@example.com', firstName: 'Camille'));

        $user = $this->users->get(new UserId(self::NEW_ID));
        self::assertTrue($user->isEmailVerified());
        self::assertFalse($user->hasPassword());
        self::assertSame('Camille', $user->profile()->firstName);
        self::assertSame([UserRegistered::class, ExternalIdentityLinked::class], $this->events->publishedClasses());
        self::assertTrue($user->id()->equals($this->owner('google-sub')));
    }

    public function testTheFirstNameFallsBackToTheEmail(): void
    {
        $this->signIn($this->profile('camille.dupont@example.com', firstName: '  '));

        self::assertSame('camille.dupont', $this->users->get(new UserId(self::NEW_ID))->profile()->firstName);
    }

    public function testAVerifiedAccountWithTheSameEmailIsLinked(): void
    {
        $existing = $this->registerVerified('alex@example.com');

        $this->signIn($this->profile('ALEX@example.com'));

        self::assertTrue($existing->id()->equals($this->owner('google-sub')));
        self::assertCount(1, $this->users->users, 'no second account');
        self::assertTrue($existing->hasPassword(), 'the password keeps working');
    }

    public function testLinkingSignsOutEveryDeviceAlreadySignedIn(): void
    {
        $existing = $this->registerVerified('alex@example.com');
        $before = $this->openSession($existing);

        $this->signIn($this->profile('alex@example.com'));

        self::assertFalse($before->isActive($this->clock->now()), 'whoever was signed in before the owner linked their provider is out');
        self::assertContains(ExternalIdentityLinked::class, $this->events->publishedClasses());
    }

    public function testAnUnverifiedAccountWithTheSameEmailIsNeverTakenOver(): void
    {
        $this->register('alex@example.com');

        $this->expectException(ExternalAccountConflict::class);
        $this->signIn($this->profile('alex@example.com'));
    }

    public function testAnEmailTheProviderDidNotVerifyIsRefused(): void
    {
        $this->expectException(ExternalSignInFailed::class);
        $this->signIn($this->profile('alex@example.com', verified: false));
    }

    public function testNoAccountIsCreatedWhenRegistrationIsClosed(): void
    {
        $this->registrationOpen = false;

        $this->expectException(RegistrationClosed::class);
        $this->signIn($this->profile('new@example.com'));
    }

    public function testAnAlreadyLinkedIdentitySignsInEvenIfTheEmailChangedAndRegistrationClosed(): void
    {
        $this->signIn($this->profile('first@example.com'));
        $this->registrationOpen = false;
        $this->events->published = [];

        $this->signIn($this->profile('renamed@example.com'));

        self::assertCount(1, $this->users->users);
        self::assertSame([], $this->events->publishedClasses());
        self::assertTrue($this->owner('google-sub')->equals(new UserId(self::NEW_ID)));
    }

    public function testAnUnknownIdentityHasNoOwner(): void
    {
        $this->expectException(ExternalSignInFailed::class);
        $this->owner('nobody');
    }

    private function profile(string $email, bool $verified = true, ?string $firstName = 'Alex'): ExternalProfile
    {
        return new ExternalProfile(IdentityProvider::Google, 'google-sub', $email, $verified, $firstName);
    }

    private function signIn(ExternalProfile $profile): void
    {
        $policy = new class($this) implements RegistrationPolicy {
            public function __construct(private readonly ExternalSignInTest $test)
            {
            }

            public function isOpen(): bool
            {
                return $this->test->isRegistrationOpen();
            }
        };

        new SignInWithExternalIdentityHandler($this->identities, $this->users, $policy, $this->sessionTerminator(), $this->uuids, $this->events, $this->clock)(new SignInWithExternalIdentity($profile, self::NEW_ID));
    }

    private function owner(string $subject): UserId
    {
        return new FindExternalIdentityOwnerHandler($this->identities)(new FindExternalIdentityOwner(IdentityProvider::Google, $subject));
    }
}
