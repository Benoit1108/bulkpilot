<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Audit\Application\Port\Pseudonyms;
use App\Audit\Domain\AuditEvent;
use App\Audit\Domain\AuditEventId;
use App\Audit\Domain\AuditEventRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use Psr\Log\LoggerInterface;

/**
 * Keeps the event for the user (« Activité du compte ») and logs it for the operator (the
 * `audit` channel). Never secrets: callers pass what changed, not how to sign in. The erasure of
 * an account is logged under its pseudonym, like the events it leaves in the table.
 */
final readonly class RecordingAuditTrail implements AuditTrail
{
    public const string ERASURE = 'identity.account_erased';

    public function __construct(private AuditEventRepository $events, private UuidGenerator $uuids, private Pseudonyms $pseudonyms, private LoggerInterface $auditLogger)
    {
    }

    public function record(UserId $userId, string $action, array $details, string $source, \DateTimeImmutable $occurredOn): void
    {
        $this->events->append(AuditEvent::record(new AuditEventId($this->uuids->generate()), $userId, $action, $details, $source, $occurredOn));
        $who = self::ERASURE === $action ? ['pseudonym' => $this->pseudonyms->of($userId)] : ['user_id' => $userId->value];
        $this->auditLogger->info($action, $who + ['source' => $source, 'occurred_on' => $occurredOn->format(\DATE_ATOM)] + $details);
    }
}
