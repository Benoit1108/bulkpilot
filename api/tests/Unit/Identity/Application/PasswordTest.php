<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\ChangePassword\ChangePassword;
use App\Identity\Application\ChangePassword\ChangePasswordHandler;
use App\Identity\Application\PasswordReset\RequestPasswordReset;
use App\Identity\Application\PasswordReset\RequestPasswordResetHandler;
use App\Identity\Application\PasswordReset\ResetPassword;
use App\Identity\Application\PasswordReset\ResetPasswordHandler;
use App\Identity\Domain\Exception\InvalidToken;
use App\Identity\Domain\Exception\WeakPassword;
use App\Identity\Domain\Exception\WrongCurrentPassword;
use App\Identity\Domain\User\PasswordChanged;
use App\Tests\Support\Identity\IdentityTestCase;

final class PasswordTest extends IdentityTestCase
{
    public function testAVerifiedUserReceivesAResetLinkThatSignsOutEveryDevice(): void
    {
        $user = $this->registerVerified();
        $phone = $this->openSession($user, 'Safari · iOS');
        $laptop = $this->openSession($user);

        $this->requestReset('ALEX@example.com');
        self::assertSame('reset', $this->mailer->sent[0]['type']);
        $this->reset($this->mailer->lastToken(), 'a brand new passphrase');

        self::assertTrue($this->hasher->verify($user->password(), 'a brand new passphrase'));
        self::assertFalse($phone->isActive($this->clock->now()));
        self::assertFalse($laptop->isActive($this->clock->now()));
        self::assertSame([PasswordChanged::class], $this->events->publishedClasses());
        self::assertSame(['identity.password_reset'], $this->audit->actions());
    }

    public function testResetRequestsRevealNothingAboutAccounts(): void
    {
        $this->register('unverified@example.com');
        $this->mailer->sent = [];

        $this->requestReset('nobody@example.com');
        $this->requestReset('unverified@example.com');
        $this->requestReset('not an email');

        self::assertSame([], $this->mailer->sent);
    }

    public function testOnlyTheLatestResetLinkWorksAndOnlyOnce(): void
    {
        $this->registerVerified();
        $this->requestReset('alex@example.com');
        $first = $this->mailer->lastToken();
        $this->requestReset('alex@example.com');
        $second = $this->mailer->lastToken();

        try {
            $this->reset($first, 'a brand new passphrase');
            self::fail('The first link must be dead');
        } catch (InvalidToken) {
        }
        $this->reset($second, 'a brand new passphrase');

        $this->expectException(InvalidToken::class);
        $this->reset($second, 'another new passphrase');
    }

    public function testAResetLinkExpiresAfterOneHour(): void
    {
        $this->registerVerified();
        $this->requestReset('alex@example.com');
        $this->clock->sleep(3600);

        $this->expectException(InvalidToken::class);
        $this->reset($this->mailer->lastToken(), 'a brand new passphrase');
    }

    public function testAWeakNewPasswordDoesNotBurnTheLink(): void
    {
        $user = $this->registerVerified();
        $this->requestReset('alex@example.com');
        $token = $this->mailer->lastToken();

        try {
            $this->reset($token, 'short');
            self::fail('Expected WeakPassword');
        } catch (WeakPassword) {
        }
        $this->reset($token, 'a brand new passphrase');

        self::assertTrue($this->hasher->verify($user->password(), 'a brand new passphrase'));
    }

    public function testChangingThePasswordKeepsOnlyTheCurrentDevice(): void
    {
        $user = $this->registerVerified();
        $current = $this->openSession($user);
        $other = $this->openSession($user, 'Safari · iOS');

        $this->changePassword()(new ChangePassword($user->id(), $current->id(), self::PASSWORD, 'a brand new passphrase'));

        self::assertTrue($this->hasher->verify($user->password(), 'a brand new passphrase'));
        self::assertTrue($current->isActive($this->clock->now()));
        self::assertFalse($other->isActive($this->clock->now()));
        self::assertSame(['identity.password_changed'], $this->audit->actions());
    }

    public function testChangingThePasswordRequiresTheCurrentOne(): void
    {
        $user = $this->registerVerified();
        $current = $this->openSession($user);

        $this->expectException(WrongCurrentPassword::class);
        $this->changePassword()(new ChangePassword($user->id(), $current->id(), 'not the password', 'a brand new passphrase'));
    }

    private function requestReset(string $email): void
    {
        new RequestPasswordResetHandler($this->users, $this->links(), $this->mailer, $this->clock)(new RequestPasswordReset($email));
    }

    private function reset(string $token, string $password): void
    {
        new ResetPasswordHandler($this->links(), $this->users, $this->hasher, $this->sessionTerminator(), $this->events, $this->audit, $this->clock)(new ResetPassword($token, $password));
    }

    private function changePassword(): ChangePasswordHandler
    {
        return new ChangePasswordHandler($this->users, $this->passwordConfirmation(), $this->hasher, $this->sessionTerminator(), $this->events, $this->audit, $this->clock);
    }
}
