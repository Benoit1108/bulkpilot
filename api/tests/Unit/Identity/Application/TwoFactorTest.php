<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\TwoFactor\ConfirmTwoFactor;
use App\Identity\Application\TwoFactor\ConfirmTwoFactorHandler;
use App\Identity\Application\TwoFactor\DisableTwoFactor;
use App\Identity\Application\TwoFactor\DisableTwoFactorHandler;
use App\Identity\Application\TwoFactor\RecoveryCodes;
use App\Identity\Application\TwoFactor\RegenerateRecoveryCodes;
use App\Identity\Application\TwoFactor\RegenerateRecoveryCodesHandler;
use App\Identity\Application\TwoFactor\SecondFactorCheck;
use App\Identity\Application\TwoFactor\StartTwoFactorSetup;
use App\Identity\Application\TwoFactor\StartTwoFactorSetupHandler;
use App\Identity\Application\TwoFactor\VerifySecondFactor;
use App\Identity\Application\TwoFactor\VerifySecondFactorHandler;
use App\Identity\Domain\Exception\EmailNotVerified;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\TwoFactorAlreadyEnabled;
use App\Identity\Domain\Exception\TwoFactorNotEnabled;
use App\Identity\Domain\Exception\TwoFactorSetupNotStarted;
use App\Identity\Domain\Exception\WrongCurrentPassword;
use App\Identity\Domain\User\RecoveryCodeUsed;
use App\Identity\Domain\User\TwoFactorDisabled;
use App\Identity\Domain\User\TwoFactorEnabled;
use App\Identity\Domain\User\User;
use App\Tests\Support\Identity\FakeKeyedHasher;
use App\Tests\Support\Identity\FakeTotpAuthenticator;
use App\Tests\Support\Identity\IdentityTestCase;
use App\Tests\Support\Identity\ReversibleCipher;

final class TwoFactorTest extends IdentityTestCase
{
    private const array CODES = ['aaaaa-11111', 'bbbbb-22222'];

    private FakeTotpAuthenticator $totp;
    private ReversibleCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new FakeTotpAuthenticator();
        $this->cipher = new ReversibleCipher();
    }

    public function testEnrolmentNeedsThePasswordAVerifiedEmailAndAFirstValidCode(): void
    {
        $user = $this->registerVerified();

        $this->start($user, 'JBSWY3DPEHPK3PXP');
        self::assertFalse($user->isTwoFactorEnabled(), 'not active before the first code');
        self::assertSame('enc:JBSWY3DPEHPK3PXP', $user->pendingTotpSecret()?->value, 'stored encrypted');

        $this->confirm($user, FakeTotpAuthenticator::codeFor($this->clock->now()));

        self::assertTrue($user->isTwoFactorEnabled());
        self::assertNull($user->pendingTotpSecret());
        self::assertCount(2, $user->recoveryCodeHashes());
        self::assertNotContains('aaaaa-11111', $user->recoveryCodeHashes(), 'recovery codes are hashed');
        self::assertSame([TwoFactorEnabled::class], $this->events->publishedClasses());
    }

    public function testEnrolmentIsRefusedWithAWrongPasswordOrAnUnverifiedEmail(): void
    {
        $unverified = $this->register('unverified@example.com');
        try {
            $this->start($unverified, 'S');
            self::fail('Expected EmailNotVerified');
        } catch (EmailNotVerified) {
        }

        $this->expectException(WrongCurrentPassword::class);
        new StartTwoFactorSetupHandler($this->users, $this->passwordConfirmation(), $this->cipher)(new StartTwoFactorSetup($this->registerVerified()->id(), 'wrong', 'S'));
    }

    public function testAWrongFirstCodeLeavesTwoFactorOff(): void
    {
        $user = $this->registerVerified();
        $this->start($user, 'S');

        try {
            $this->confirm($user, '000000');
            self::fail('Expected InvalidSecondFactorCode');
        } catch (InvalidSecondFactorCode) {
        }

        self::assertFalse($user->isTwoFactorEnabled());
        self::assertNotNull($user->pendingTotpSecret(), 'the user can try again');
    }

    public function testConfirmingWithoutSetupOrTwiceIsRefused(): void
    {
        $user = $this->registerVerified();
        try {
            $this->confirm($user, '000000');
            self::fail('Expected TwoFactorSetupNotStarted');
        } catch (TwoFactorSetupNotStarted) {
        }

        $this->enable($user);
        $this->expectException(TwoFactorAlreadyEnabled::class);
        $this->start($user, 'S');
    }

    public function testAnAppCodeIsAcceptedOnceAndOnlyWithinTheDriftWindow(): void
    {
        $user = $this->enable($this->registerVerified());
        $this->clock->sleep(30);

        $this->verify($user, FakeTotpAuthenticator::codeFor($this->clock->now()));
        $this->expectInvalid(fn () => $this->verify($user, FakeTotpAuthenticator::codeFor($this->clock->now())), 'replayed code');

        $this->clock->sleep(30);
        $this->verify($user, FakeTotpAuthenticator::codeFor($this->clock->now()->modify('+30 seconds')));
        $this->expectInvalid(fn () => $this->verify($user, FakeTotpAuthenticator::codeFor($this->clock->now()->modify('+90 seconds'))), 'too far in the future');
        $this->expectInvalid(fn () => $this->verify($user, '123456'), 'wrong code');
    }

    public function testARecoveryCodeWorksOnceWhateverItsFormatting(): void
    {
        $user = $this->enable($this->registerVerified());

        $this->verify($user, ' AAAAA11111 ');

        self::assertCount(1, $user->recoveryCodeHashes());
        self::assertSame([RecoveryCodeUsed::class], $this->events->publishedClasses());
        $this->expectInvalid(fn () => $this->verify($user, 'aaaaa-11111'), 'already used');
        $this->expectInvalid(fn () => $this->verify($user, 'zzzzz-99999'), 'unknown');
    }

    public function testRecoveryCodesCanBeReplacedWithThePassword(): void
    {
        $user = $this->enable($this->registerVerified());
        $regenerate = new RegenerateRecoveryCodesHandler($this->users, $this->passwordConfirmation(), new RecoveryCodes(new FakeKeyedHasher()), $this->audit, $this->clock);

        $regenerate(new RegenerateRecoveryCodes($user->id(), self::PASSWORD, ['ccccc-33333']));

        $this->expectInvalid(fn () => $this->verify($user, 'aaaaa-11111'), 'old codes are dead');
        $this->verify($user, 'ccccc-33333');

        $this->expectException(WrongCurrentPassword::class);
        $regenerate(new RegenerateRecoveryCodes($user->id(), 'wrong', ['ddddd-44444']));
    }

    public function testDisablingNeedsThePasswordAndForgetsEverything(): void
    {
        $user = $this->enable($this->registerVerified());
        $disable = new DisableTwoFactorHandler($this->users, $this->passwordConfirmation(), $this->events, $this->clock);

        try {
            $disable(new DisableTwoFactor($user->id(), 'wrong'));
            self::fail('Expected WrongCurrentPassword');
        } catch (WrongCurrentPassword) {
        }
        $disable(new DisableTwoFactor($user->id(), self::PASSWORD));

        self::assertFalse($user->isTwoFactorEnabled());
        self::assertNull($user->totpSecret());
        self::assertSame([], $user->recoveryCodeHashes());
        self::assertSame([TwoFactorDisabled::class], $this->events->publishedClasses());
        $this->expectException(TwoFactorNotEnabled::class);
        $disable(new DisableTwoFactor($user->id(), self::PASSWORD));
    }

    public function testASecondFactorIsRefusedForAccountsWithoutTwoFactor(): void
    {
        $this->expectInvalid(fn () => $this->verify($this->registerVerified(), '123456'), 'no second factor configured');
    }

    private function start(User $user, string $secret): void
    {
        new StartTwoFactorSetupHandler($this->users, $this->passwordConfirmation(), $this->cipher)(new StartTwoFactorSetup($user->id(), self::PASSWORD, $secret));
    }

    private function confirm(User $user, string $code): void
    {
        new ConfirmTwoFactorHandler($this->users, $this->cipher, $this->totp, new RecoveryCodes(new FakeKeyedHasher()), $this->events, $this->clock)(new ConfirmTwoFactor($user->id(), $code, self::CODES));
    }

    private function enable(User $user): User
    {
        $this->start($user, 'S');
        $this->confirm($user, FakeTotpAuthenticator::codeFor($this->clock->now()));
        $this->events->published = [];

        return $user;
    }

    private function verify(User $user, string $code): void
    {
        new VerifySecondFactorHandler($this->users, new SecondFactorCheck($this->cipher, $this->totp, new RecoveryCodes(new FakeKeyedHasher())), $this->events, $this->clock)(new VerifySecondFactor($user->id(), $code));
    }

    private function expectInvalid(\Closure $attempt, string $case): void
    {
        try {
            $attempt();
            self::fail('Expected InvalidSecondFactorCode: '.$case);
        } catch (InvalidSecondFactorCode $e) {
            self::assertSame('identity.invalid_second_factor_code', $e->errorCode(), $case);
            $this->events->published = [];
        }
    }
}
