<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

use App\Identity\Domain\External\ExternalIdentityLinked;
use App\Identity\Domain\User\EmailVerified;
use App\Identity\Domain\User\PasswordChanged;
use App\Identity\Domain\User\RecoveryCodeUsed;
use App\Identity\Domain\User\TwoFactorDisabled;
use App\Identity\Domain\User\TwoFactorEnabled;
use App\Identity\Domain\User\UserRegistered;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use Psr\Log\LoggerInterface;

/**
 * Security journal (spec §101/§105). A queryable audit log arrives in phase 8; until then the
 * dedicated "audit" log channel records who did what, never secrets or personal data.
 */
final readonly class LogSecurityEvent implements DomainEventSubscriber
{
    public function __construct(private LoggerInterface $auditLogger)
    {
    }

    public function __invoke(UserRegistered|EmailVerified|PasswordChanged|TwoFactorEnabled|TwoFactorDisabled|RecoveryCodeUsed|ExternalIdentityLinked $event): void
    {
        $this->auditLogger->info('identity.{event}', [
            'event' => new \ReflectionClass($event)->getShortName(),
            'user_id' => $event->userId->value,
            'occurred_on' => $event->occurredOn()->format(\DATE_ATOM),
        ]);
    }
}
