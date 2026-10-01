<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

use App\Identity\Domain\External\ExternalIdentityLinked;
use App\Identity\Domain\User\EmailVerified;
use App\Identity\Domain\User\RecoveryCodeUsed;
use App\Identity\Domain\User\TwoFactorDisabled;
use App\Identity\Domain\User\TwoFactorEnabled;
use App\Identity\Domain\User\UserRegistered;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;

/**
 * The account's security events in the audit journal (spec §101, §105): who did what, never a
 * secret nor the email address. A password change is recorded by the use case, which knows
 * whether it was changed or reset.
 */
final readonly class LogSecurityEvent implements DomainEventSubscriber
{
    public function __construct(private AuditTrail $audit)
    {
    }

    public function __invoke(UserRegistered|EmailVerified|TwoFactorEnabled|TwoFactorDisabled|RecoveryCodeUsed|ExternalIdentityLinked $event): void
    {
        [$action, $details] = match (true) {
            $event instanceof UserRegistered => ['identity.registered', []],
            $event instanceof EmailVerified => ['identity.email_verified', []],
            $event instanceof TwoFactorEnabled => ['identity.two_factor_enabled', []],
            $event instanceof TwoFactorDisabled => ['identity.two_factor_disabled', []],
            $event instanceof RecoveryCodeUsed => ['identity.recovery_code_used', ['remaining' => $event->remaining]],
            $event instanceof ExternalIdentityLinked => ['identity.external_identity_linked', ['provider' => $event->provider->value]],
        };
        $this->audit->record($event->userId, $action, $details, 'user', $event->occurredOn());
    }
}
