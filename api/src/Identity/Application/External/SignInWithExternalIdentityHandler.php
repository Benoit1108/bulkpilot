<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Identity\Application\Port\RegistrationPolicy;
use App\Identity\Application\Session\SessionTerminator;
use App\Identity\Domain\Exception\ExternalAccountConflict;
use App\Identity\Domain\Exception\RegistrationClosed;
use App\Identity\Domain\External\ExternalIdentity;
use App\Identity\Domain\External\ExternalIdentityId;
use App\Identity\Domain\External\ExternalIdentityRepository;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use Psr\Clock\ClockInterface;

/**
 * Resolves the account behind an external identity:
 *  1. already linked → that account;
 *  2. same email → linked only if both the provider and our account vouch for the address; every
 *     device already signed in to that account is signed out (whoever may have created it before
 *     its owner loses access);
 *  3. otherwise → a new account (if registration is open), email verified, no password.
 */
final readonly class SignInWithExternalIdentityHandler implements CommandHandler
{
    public function __construct(
        private ExternalIdentityRepository $identities,
        private UserRepository $users,
        private RegistrationPolicy $registrationPolicy,
        private SessionTerminator $sessions,
        private UuidGenerator $uuids,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SignInWithExternalIdentity $command): void
    {
        $profile = $command->profile;
        if (null !== $this->identities->find($profile->provider, $profile->subject)) {
            return;
        }
        if (!$profile->emailVerified) {
            throw ExternalSignInFailed::because('the provider did not verify the email address');
        }

        $now = $this->clock->now();
        $email = new EmailAddress($profile->email);
        $user = $this->users->findByEmail($email);

        if (null !== $user && !$user->isEmailVerified()) {
            throw ExternalAccountConflict::create();
        }
        if (null !== $user) {
            $this->sessions->terminateAll($user->id(), except: null, now: $now);
        }
        if (null === $user) {
            if (!$this->registrationPolicy->isOpen()) {
                throw RegistrationClosed::create();
            }
            $user = User::registerExternally(new UserId($command->newUserId), $email, new Profile(self::firstName($profile), $now), $now);
            $this->users->save($user);
        }

        $identity = ExternalIdentity::link(new ExternalIdentityId($this->uuids->generate()), $user->id(), $profile->provider, $profile->subject, $now);
        $this->identities->save($identity);
        $this->eventBus->publish(...$user->pullDomainEvents(), ...$identity->pullDomainEvents());
    }

    private static function firstName(ExternalProfile $profile): string
    {
        $name = trim((string) $profile->firstName);

        if ('' !== $name) {
            return mb_substr($name, 0, 50);
        }
        $local = strstr($profile->email, '@', true);

        return mb_substr(false === $local || '' === $local ? $profile->email : $local, 0, 50);
    }
}
