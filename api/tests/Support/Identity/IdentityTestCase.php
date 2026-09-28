<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\RegistrationPolicy;
use App\Identity\Application\Register\RegisterUser;
use App\Identity\Application\Register\RegisterUserHandler;
use App\Identity\Application\Session\SessionTerminator;
use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Application\Token\OneTimeLinks;
use App\Identity\Application\VerifyEmail\EmailVerificationSender;
use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\User\User;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use App\Tests\Support\Shared\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Wires the Identity use cases on in-memory adapters.
 */
abstract class IdentityTestCase extends TestCase
{
    protected const string PASSWORD = 'correct horse battery';

    protected InMemoryUserRepository $users;
    protected InMemoryUserSessionRepository $sessions;
    protected InMemoryOneTimeTokenRepository $tokens;
    protected FakePasswordHasher $hasher;
    protected SpyIdentityMailer $mailer;
    protected SpyEventBus $events;
    protected SequentialUuidGenerator $uuids;
    protected SequentialSecretGenerator $secrets;
    protected MockClock $clock;
    protected bool $registrationOpen = true;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->sessions = new InMemoryUserSessionRepository();
        $this->tokens = new InMemoryOneTimeTokenRepository();
        $this->hasher = new FakePasswordHasher();
        $this->mailer = new SpyIdentityMailer();
        $this->events = new SpyEventBus();
        $this->uuids = new SequentialUuidGenerator();
        $this->secrets = new SequentialSecretGenerator();
        $this->clock = new MockClock('2026-09-28 10:00:00');
    }

    protected function registerHandler(): RegisterUserHandler
    {
        $policy = new class($this) implements RegistrationPolicy {
            public function __construct(private readonly IdentityTestCase $test)
            {
            }

            public function isOpen(): bool
            {
                return $this->test->isRegistrationOpen();
            }
        };

        return new RegisterUserHandler($policy, $this->users, $this->hasher, $this->verificationSender(), $this->events, $this->clock);
    }

    public function isRegistrationOpen(): bool
    {
        return $this->registrationOpen;
    }

    protected function verificationSender(): EmailVerificationSender
    {
        return new EmailVerificationSender($this->links(), $this->mailer);
    }

    protected function links(): OneTimeLinks
    {
        return new OneTimeLinks($this->tokens, $this->secrets, $this->uuids);
    }

    protected function passwordConfirmation(): PasswordConfirmation
    {
        return new PasswordConfirmation($this->hasher);
    }

    protected function sessionTerminator(): SessionTerminator
    {
        return new SessionTerminator($this->sessions);
    }

    protected function register(string $email = 'alex@example.com'): User
    {
        $id = $this->uuids->generate();
        ($this->registerHandler())(new RegisterUser($id, $email, self::PASSWORD, 'Alex'));
        $this->events->published = [];

        return $this->users->get(new UserId($id));
    }

    protected function registerVerified(string $email = 'alex@example.com'): User
    {
        $user = $this->register($email);
        $user->verifyEmail($this->clock->now());
        $user->pullDomainEvents();
        $this->mailer->sent = [];

        return $user;
    }

    protected function openSession(User $user, string $device = 'Firefox · Linux'): UserSession
    {
        $session = UserSession::open(new UserSessionId($this->uuids->generate()), $user->id(), $device, $this->clock->now());
        $this->sessions->save($session);

        return $session;
    }
}
