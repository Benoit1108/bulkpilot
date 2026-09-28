<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Register\RegisterUser;
use App\Identity\Application\VerifyEmail\ResendEmailVerification;
use App\Identity\Application\VerifyEmail\ResendEmailVerificationHandler;
use App\Identity\Application\VerifyEmail\VerifyEmail;
use App\Identity\Application\VerifyEmail\VerifyEmailHandler;
use App\Identity\Domain\Exception\EmailAlreadyUsed;
use App\Identity\Domain\Exception\EmailAlreadyVerified;
use App\Identity\Domain\Exception\InvalidToken;
use App\Identity\Domain\Exception\RegistrationClosed;
use App\Identity\Domain\Exception\WeakPassword;
use App\Identity\Domain\User\EmailVerified;
use App\Identity\Domain\User\UserRegistered;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Identity\IdentityTestCase;

final class RegistrationTest extends IdentityTestCase
{
    private const string ID = '0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b';

    public function testRegisteringCreatesAnUnverifiedAccountAndEmailsAVerificationLink(): void
    {
        ($this->registerHandler())(new RegisterUser(self::ID, 'Alex@Example.com', self::PASSWORD, 'Alex'));

        $user = $this->users->get(new UserId(self::ID));
        self::assertSame('alex@example.com', $user->email()->value);
        self::assertFalse($user->isEmailVerified());
        self::assertTrue($this->hasher->verify($user->password(), self::PASSWORD));
        self::assertSame([['type' => 'verification', 'to' => 'alex@example.com', 'token' => $this->mailer->lastToken()]], $this->mailer->sent);
        self::assertSame([UserRegistered::class], $this->events->publishedClasses());
    }

    public function testRegistrationCanBeClosed(): void
    {
        $this->registrationOpen = false;

        $this->expectException(RegistrationClosed::class);
        ($this->registerHandler())(new RegisterUser(self::ID, 'alex@example.com', self::PASSWORD, 'Alex'));
    }

    public function testAnEmailCanOnlyBeUsedOnceWhateverItsCase(): void
    {
        $this->register('alex@example.com');

        $this->expectException(EmailAlreadyUsed::class);
        ($this->registerHandler())(new RegisterUser(self::ID, 'ALEX@example.com', self::PASSWORD, 'Alex'));
    }

    public function testWeakPasswordsAreRefusedBeforeAnythingIsStored(): void
    {
        try {
            ($this->registerHandler())(new RegisterUser(self::ID, 'alex@example.com', 'short', 'Alex'));
            self::fail('Expected WeakPassword');
        } catch (WeakPassword) {
        }

        self::assertSame([], $this->users->users);
        self::assertSame([], $this->mailer->sent);
    }

    public function testTheEmailedLinkVerifiesTheAddressOnce(): void
    {
        $user = $this->register();
        $token = $this->mailer->lastToken();

        $this->verify($token);

        self::assertTrue($user->isEmailVerified());
        self::assertSame([EmailVerified::class], $this->events->publishedClasses());
        $this->expectException(InvalidToken::class);
        $this->verify($token);
    }

    public function testATamperedLinkIsRejected(): void
    {
        $this->register();
        [$id] = explode('.', $this->mailer->lastToken(), 2);

        $this->expectException(InvalidToken::class);
        $this->verify($id.'.forged');
    }

    public function testMalformedLinksAreRejectedAsInvalid(): void
    {
        $values = ['', 'no-dot', 'not-a-uuid.secret', '0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b.', '0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b.unknown']; // gitleaks:allow (fake token)
        $rejected = [];
        foreach ($values as $value) {
            try {
                $this->verify($value);
            } catch (InvalidToken) {
                $rejected[] = $value;
            }
        }

        self::assertSame($values, $rejected);
    }

    public function testResendingInvalidatesThePreviousLink(): void
    {
        $user = $this->register();
        $first = $this->mailer->lastToken();

        new ResendEmailVerificationHandler($this->users, $this->verificationSender(), $this->clock)(new ResendEmailVerification($user->id()));
        $second = $this->mailer->lastToken();

        self::assertNotSame($first, $second);
        try {
            $this->verify($first);
            self::fail('The first link must be dead');
        } catch (InvalidToken) {
        }
        $this->verify($second);
        self::assertTrue($user->isEmailVerified());
    }

    public function testNothingIsResentOnceVerified(): void
    {
        $user = $this->registerVerified();

        $this->expectException(EmailAlreadyVerified::class);
        new ResendEmailVerificationHandler($this->users, $this->verificationSender(), $this->clock)(new ResendEmailVerification($user->id()));
    }

    private function verify(string $token): void
    {
        new VerifyEmailHandler($this->links(), $this->users, $this->events, $this->clock)(new VerifyEmail($token));
    }
}
