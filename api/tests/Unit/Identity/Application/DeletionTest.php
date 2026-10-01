<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Deletion\AccountErased;
use App\Identity\Application\Deletion\AnnounceDeletion;
use App\Identity\Application\Deletion\EraseAccount;
use App\Identity\Application\Deletion\EraseAccountHandler;
use App\Identity\Application\Deletion\EraseDueAccounts;
use App\Identity\Application\Deletion\EraseDueAccountsHandler;
use App\Identity\Application\Deletion\ScheduleAccountDeletion;
use App\Identity\Application\Deletion\ScheduleAccountDeletionHandler;
use App\Identity\Application\Session\OpenUserSession;
use App\Identity\Application\Session\OpenUserSessionHandler;
use App\Identity\Application\TwoFactor\ConfirmTwoFactor;
use App\Identity\Application\TwoFactor\ConfirmTwoFactorHandler;
use App\Identity\Application\TwoFactor\RecoveryCodes;
use App\Identity\Application\TwoFactor\SecondFactorCheck;
use App\Identity\Application\TwoFactor\StartTwoFactorSetup;
use App\Identity\Application\TwoFactor\StartTwoFactorSetupHandler;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\ReauthenticationRequired;
use App\Identity\Domain\Exception\WrongCurrentPassword;
use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\User\AccountDeletionCancelled;
use App\Identity\Domain\User\AccountDeletionScheduled;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Identity\FakeKeyedHasher;
use App\Tests\Support\Identity\FakeTotpAuthenticator;
use App\Tests\Support\Identity\IdentityTestCase;
use App\Tests\Support\Identity\InMemoryPreferencesRepository;
use App\Tests\Support\Identity\ReversibleCipher;
use App\Tests\Support\Identity\SpyPersonalData;
use App\Tests\Support\Shared\SpyCommandBus;

final class DeletionTest extends IdentityTestCase
{
    private FakeTotpAuthenticator $totp;
    private ReversibleCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new FakeTotpAuthenticator();
        $this->cipher = new ReversibleCipher();
    }

    public function testThePasswordClosesTheAccountAtOnceAndRevokesEverySession(): void
    {
        $user = $this->registerVerified();
        $laptop = $this->openSession($user);
        $phone = $this->openSession($user, 'Safari · iOS');

        $this->schedule($user, self::PASSWORD);

        self::assertTrue($user->isDeletionScheduled());
        self::assertSame('2026-10-05T10:00:00+00:00', $user->deletionDueAt()?->format(\DATE_ATOM), '7 days later');
        self::assertFalse($laptop->isActive($this->clock->now()));
        self::assertFalse($phone->isActive($this->clock->now()), 'this device too');
        self::assertSame([AccountDeletionScheduled::class], $this->events->publishedClasses());
    }

    public function testAWrongPasswordChangesNothing(): void
    {
        $user = $this->registerVerified();
        $session = $this->openSession($user);

        try {
            $this->schedule($user, 'wrong');
            self::fail('Expected WrongCurrentPassword');
        } catch (WrongCurrentPassword) {
        }

        self::assertFalse($user->isDeletionScheduled());
        self::assertTrue($session->isActive($this->clock->now()));
    }

    public function testWithTwoFactorOnTheCodeIsNeededTooAndARecoveryCodeWorks(): void
    {
        $user = $this->withTwoFactor($this->registerVerified());

        foreach ([null, '000000'] as $code) {
            try {
                $this->schedule($user, self::PASSWORD, $code);
                self::fail('Expected InvalidSecondFactorCode');
            } catch (InvalidSecondFactorCode) {
            }
        }
        self::assertFalse($user->isDeletionScheduled());

        $this->schedule($user, self::PASSWORD, 'aaaaa-11111');
        self::assertTrue($user->isDeletionScheduled());
        self::assertCount(1, $user->recoveryCodeHashes(), 'the recovery code is burnt');
    }

    public function testTheAppCodeOfTheMomentWorks(): void
    {
        $user = $this->withTwoFactor($this->registerVerified());
        $this->clock->sleep(30);

        $this->schedule($user, self::PASSWORD, FakeTotpAuthenticator::codeFor($this->clock->now()));

        self::assertTrue($user->isDeletionScheduled());
    }

    public function testWithoutAPasswordASignInOfTheLastTenMinutesOnThisDeviceStandsForIt(): void
    {
        $user = User::registerExternally(new UserId($this->uuids->generate()), new EmailAddress('google@example.com'), new Profile('Sam', $this->clock->now()), $this->clock->now());
        $this->users->save($user);
        $session = $this->openSession($user);
        $other = $this->openSession($this->registerVerified());
        $this->clock->sleep(601);

        foreach ([null, $session->id(), $other->id()] as $device) {
            try {
                $this->schedule($user, '', null, $device);
                self::fail('Expected ReauthenticationRequired');
            } catch (ReauthenticationRequired) {
            }
        }
        self::assertFalse($user->isDeletionScheduled(), 'too old, missing, or another account\'s sign-in');

        $fresh = $this->openSession($user);
        $this->schedule($user, '', null, $fresh->id());
        self::assertTrue($user->isDeletionScheduled());
    }

    public function testTheTenMinutesAreTenMinutesAndARevokedDeviceStandsForNothing(): void
    {
        $user = User::registerExternally(new UserId($this->uuids->generate()), new EmailAddress('google@example.com'), new Profile('Sam', $this->clock->now()), $this->clock->now());
        $this->users->save($user);
        $revoked = $this->openSession($user);
        $revoked->revoke($this->clock->now());
        try {
            $this->schedule($user, '', null, $revoked->id());
            self::fail('A revoked device is signed out: its sign-in proves nothing.');
        } catch (ReauthenticationRequired) {
        }

        $session = $this->openSession($user);
        $this->clock->sleep(ScheduleAccountDeletionHandler::RECENT_SIGN_IN_MINUTES * 60);
        $this->schedule($user, '', null, $session->id());
        self::assertTrue($user->isDeletionScheduled(), 'exactly ten minutes after: still recent');
    }

    public function testAskingAgainKeepsTheFirstDate(): void
    {
        $user = $this->registerVerified();
        $this->schedule($user, self::PASSWORD);
        $this->clock->sleep(3600);
        $this->events->published = [];

        $this->schedule($user, self::PASSWORD);

        self::assertSame('2026-10-05T10:00:00+00:00', $user->deletionDueAt()?->format(\DATE_ATOM));
        self::assertSame([], $this->events->publishedClasses());
    }

    public function testSigningInDuringTheDelayCancelsTheDeletionOnce(): void
    {
        $user = $this->registerVerified();
        $this->schedule($user, self::PASSWORD);
        $this->events->published = [];
        $this->clock->sleep(86400 * 3);

        $this->signIn($user);
        self::assertFalse($user->isDeletionScheduled());
        self::assertNull($user->deletionDueAt());
        self::assertSame([AccountDeletionCancelled::class], $this->events->publishedClasses());

        $this->events->published = [];
        $this->signIn($user);
        self::assertSame([], $this->events->publishedClasses(), 'nothing to cancel any more');
    }

    public function testEachStepIsEmailedAndRecordedTheDateInTheUsersTimeZone(): void
    {
        $user = $this->registerVerified();
        $preferences = new InMemoryPreferencesRepository($this->users);
        $preferences->save(Preferences::defaults($user->id(), $this->clock->now()));
        $preferences->of($user->id())?->moveToTimeZone(new TimeZone('Pacific/Auckland'), $this->clock->now());
        $announce = new AnnounceDeletion($this->users, $preferences, $this->mailer, $this->audit);
        $this->clock->modify('2026-09-28 13:30:00');
        $this->schedule($user, self::PASSWORD);
        $event = $this->events->published[0];
        self::assertInstanceOf(AccountDeletionScheduled::class, $event);

        $announce($event);
        $announce(new AccountDeletionCancelled($user->id(), $this->clock->now()));

        self::assertSame(['deletion_scheduled', 'deletion_cancelled'], array_column($this->mailer->sent, 'type'));
        self::assertSame('2026-10-06T02:30:00+13:00', $this->mailer->sent[0]['token'], 'the due day in Auckland is already the 6th');
        self::assertSame(['identity.deletion_scheduled', 'identity.deletion_cancelled'], $this->audit->actions());
        self::assertSame(['due_on' => '2026-10-06'], $this->audit->recorded[0]['details']);
    }

    public function testTheScheduleSendsOneErasurePerDueAccount(): void
    {
        $due = $this->registerVerified('due@example.com');
        $this->schedule($due, self::PASSWORD);
        $this->clock->sleep(86400 * 2);
        $later = $this->registerVerified('later@example.com');
        $this->schedule($later, self::PASSWORD);
        $this->registerVerified('kept@example.com');
        $bus = new SpyCommandBus();

        $this->clock->modify('2026-10-05 10:00:00');
        new EraseDueAccountsHandler($this->users, $bus, $this->clock)(new EraseDueAccounts());

        self::assertSame([$due->id()->value], array_map(static fn (Command $command): string => $command instanceof EraseAccount ? $command->userId->value : '', $bus->dispatched));
    }

    public function testErasureRunsEveryContextIdentityLastThenSaysGoodbye(): void
    {
        $user = $this->registerVerified();
        $this->schedule($user, self::PASSWORD);
        /** @var \ArrayObject<int, string> $erased */
        $erased = new \ArrayObject();
        $sources = [new SpyPersonalData('identity', $erased, last: true), new SpyPersonalData('nutrition', $erased), new SpyPersonalData('media', $erased)];
        $erase = new EraseAccountHandler($this->users, $sources, $this->mailer, $this->audit, $this->events, $this->clock);

        $this->clock->modify('2026-10-05 09:59:59');
        $erase(new EraseAccount($user->id()));
        self::assertSame([], $erased->getArrayCopy(), 'not before the 7 days');

        $this->clock->modify('2026-10-05 10:00:00');
        $erase(new EraseAccount($user->id()));

        $id = $user->id()->value;
        self::assertSame(["nutrition:{$id}", "media:{$id}", "identity:{$id}"], $erased->getArrayCopy());
        self::assertSame(['identity.account_erased'], $this->audit->actions(), 'recorded before the erasure pseudonymises it');
        self::assertSame(['type' => 'account_erased', 'to' => 'alex@example.com', 'token' => ''], end($this->mailer->sent));
        self::assertTrue($this->published(AccountErased::class), 'remembered next to the backups once it committed');
    }

    public function testAnAccountRestoredFromABackupIsErasedAgainWithoutAnEmail(): void
    {
        $user = $this->registerVerified();
        /** @var \ArrayObject<int, string> $erased */
        $erased = new \ArrayObject();
        $sent = \count($this->mailer->sent);
        $erase = new EraseAccountHandler($this->users, [new SpyPersonalData('nutrition', $erased), new SpyPersonalData('identity', $erased, last: true)], $this->mailer, $this->audit, $this->events, $this->clock);

        $erase(new EraseAccount($user->id(), restored: true));

        $id = $user->id()->value;
        self::assertSame(["nutrition:{$id}", "identity:{$id}"], $erased->getArrayCopy(), 'whatever its state: it was erased before');
        self::assertCount($sent, $this->mailer->sent, 'no farewell twice');
    }

    public function testAFailedErasureChangesNothingAndSaysNothing(): void
    {
        $user = $this->registerVerified();
        $this->schedule($user, self::PASSWORD);
        /** @var \ArrayObject<int, string> $erased */
        $erased = new \ArrayObject();
        $sent = \count($this->mailer->sent);
        $this->clock->modify('2026-10-05 10:00:00');
        $failing = new EraseAccountHandler($this->users, [new SpyPersonalData('nutrition', $erased), new SpyPersonalData('media', $erased, failing: true)], $this->mailer, $this->audit, $this->events, $this->clock);

        try {
            $failing(new EraseAccount($user->id()));
            self::fail('the failure must reach the bus, which rolls the transaction back and retries');
        } catch (\RuntimeException) {
        }
        self::assertCount($sent, $this->mailer->sent, 'no farewell for an erasure that did not happen');
        self::assertFalse($this->published(AccountErased::class));
    }

    public function testAnAccountSignedInAgainOrAlreadyGoneIsLeftAlone(): void
    {
        $user = $this->registerVerified();
        $this->schedule($user, self::PASSWORD);
        $this->signIn($user);
        /** @var \ArrayObject<int, string> $erased */
        $erased = new \ArrayObject();
        $erase = new EraseAccountHandler($this->users, [new SpyPersonalData('nutrition', $erased)], $this->mailer, $this->audit, $this->events, $this->clock);
        $this->clock->modify('2026-10-06 10:00:00');

        $erase(new EraseAccount($user->id()));
        unset($this->users->users[$user->id()->value]);
        $erase(new EraseAccount($user->id()));

        self::assertSame([], $erased->getArrayCopy());
        self::assertNotContains('identity.account_erased', $this->audit->actions());
    }

    /**
     * @param class-string $event
     */
    private function published(string $event): bool
    {
        return \in_array($event, $this->events->publishedClasses(), true);
    }

    private function schedule(User $user, string $password, ?string $code = null, ?UserSessionId $session = null): void
    {
        new ScheduleAccountDeletionHandler(
            $this->users,
            $this->sessions,
            $this->passwordConfirmation(),
            new SecondFactorCheck($this->cipher, $this->totp, new RecoveryCodes(new FakeKeyedHasher())),
            $this->sessionTerminator(),
            $this->events,
            $this->clock,
        )(new ScheduleAccountDeletion($user->id(), $password, $code, $session));
    }

    private function signIn(User $user): void
    {
        new OpenUserSessionHandler($this->sessions, $this->users, $this->events, $this->audit, $this->clock)(new OpenUserSession(new UserSessionId($this->uuids->generate()), $user->id(), 'Firefox · Linux'));
    }

    private function withTwoFactor(User $user): User
    {
        new StartTwoFactorSetupHandler($this->users, $this->passwordConfirmation(), $this->cipher)(new StartTwoFactorSetup($user->id(), self::PASSWORD, 'S'));
        new ConfirmTwoFactorHandler($this->users, $this->cipher, $this->totp, new RecoveryCodes(new FakeKeyedHasher()), $this->events, $this->clock)(new ConfirmTwoFactor($user->id(), FakeTotpAuthenticator::codeFor($this->clock->now()), ['aaaaa-11111', 'bbbbb-22222']));
        $this->events->published = [];

        return $user;
    }
}
