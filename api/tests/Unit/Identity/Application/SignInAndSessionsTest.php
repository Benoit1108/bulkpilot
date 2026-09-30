<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\Account\GetAccountHandler;
use App\Identity\Application\Profile\UpdateProfile;
use App\Identity\Application\Profile\UpdateProfileHandler;
use App\Identity\Application\Session\ListActiveSessions;
use App\Identity\Application\Session\ListActiveSessionsHandler;
use App\Identity\Application\Session\OpenUserSession;
use App\Identity\Application\Session\OpenUserSessionHandler;
use App\Identity\Application\Session\RevokeUserSession;
use App\Identity\Application\Session\RevokeUserSessionHandler;
use App\Identity\Application\SignIn\CheckCredentials;
use App\Identity\Application\SignIn\CheckCredentialsHandler;
use App\Identity\Domain\Exception\InvalidCredentials;
use App\Identity\Domain\Exception\InvalidProfile;
use App\Identity\Domain\Exception\SessionNotFound;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\User\Sex;
use App\Tests\Support\Identity\IdentityTestCase;
use App\Tests\Support\Identity\InMemoryPreferencesRepository;

final class SignInAndSessionsTest extends IdentityTestCase
{
    public function testTheRightPasswordIdentifiesTheUser(): void
    {
        $user = $this->register();

        self::assertTrue($user->id()->equals($this->checkCredentials('ALEX@example.com', self::PASSWORD)));
    }

    public function testUnknownEmailsAndWrongPasswordsFailTheSameWay(): void
    {
        $this->register();

        foreach ([['nobody@example.com', self::PASSWORD], ['alex@example.com', 'wrong password'], ['not an email', 'x']] as [$email, $password]) {
            try {
                $this->checkCredentials($email, $password);
                self::fail('Expected InvalidCredentials');
            } catch (InvalidCredentials $e) {
                self::assertSame('identity.invalid_credentials', $e->errorCode());
            }
        }
        self::assertSame(2, $this->hasher->dummyVerifications, 'unknown users still cost a hash verification');
    }

    public function testDevicesAreListedMostRecentFirstWithTheCurrentOneFlagged(): void
    {
        $user = $this->register();
        $laptop = new UserSessionId($this->uuids->generate());
        $phone = new UserSessionId($this->uuids->generate());
        $open = new OpenUserSessionHandler($this->sessions, $this->clock);
        $open(new OpenUserSession($laptop, $user->id(), 'Firefox · Linux'));
        $this->clock->sleep(600);
        $open(new OpenUserSession($phone, $user->id(), 'Safari · iOS'));

        $views = new ListActiveSessionsHandler($this->sessions, $this->clock)(new ListActiveSessions($user->id(), $laptop));

        self::assertSame(['Safari · iOS', 'Firefox · Linux'], array_map(static fn ($view) => $view->deviceLabel, $views));
        self::assertSame([false, true], array_map(static fn ($view) => $view->current, $views));
    }

    public function testADeviceCanBeSignedOutButOnlyByItsOwner(): void
    {
        $alex = $this->register('alex@example.com');
        $sam = $this->register('sam@example.com');
        $samSession = $this->openSession($sam);
        $revoke = new RevokeUserSessionHandler($this->sessions, $this->clock);

        try {
            $revoke(new RevokeUserSession($alex->id(), $samSession->id()));
            self::fail("Alex must not see Sam's session");
        } catch (SessionNotFound) {
        }
        self::assertTrue($samSession->isActive($this->clock->now()));

        $revoke(new RevokeUserSession($sam->id(), $samSession->id()));
        self::assertFalse($samSession->isActive($this->clock->now()));
        self::assertSame([], new ListActiveSessionsHandler($this->sessions, $this->clock)(new ListActiveSessions($sam->id(), null)));

        $this->expectException(SessionNotFound::class);
        $revoke(new RevokeUserSession($sam->id(), $samSession->id()));
    }

    public function testTheAccountExposesTheProfile(): void
    {
        $user = $this->registerVerified();

        new UpdateProfileHandler($this->users, $this->clock)(new UpdateProfile($user->id(), 'Alexandre', new \DateTimeImmutable('2000-01-15'), Sex::Male, 178));
        $view = new GetAccountHandler($this->users, new InMemoryPreferencesRepository($this->users))(new GetAccount($user->id()));

        self::assertSame('alex@example.com', $view->email);
        self::assertTrue($view->emailVerified);
        self::assertSame('Alexandre', $view->firstName);
        self::assertSame('2000-01-15', $view->birthDate?->format('Y-m-d'));
        self::assertSame(Sex::Male, $view->sex);
        self::assertSame(178, $view->heightCm);
    }

    public function testAnImplausibleProfileIsRejected(): void
    {
        $user = $this->register();

        $this->expectException(InvalidProfile::class);
        new UpdateProfileHandler($this->users, $this->clock)(new UpdateProfile($user->id(), 'Alex', null, null, 40));
    }

    private function checkCredentials(string $email, string $password): \App\Shared\Domain\Identifier\UserId
    {
        return new CheckCredentialsHandler($this->users, $this->hasher)(new CheckCredentials($email, $password));
    }
}
