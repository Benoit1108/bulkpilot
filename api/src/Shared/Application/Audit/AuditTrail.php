<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit;

use App\Shared\Domain\Identifier\UserId;

/**
 * The journal of important actions (spec §10, §105, ADR-0030), implemented by the Audit context:
 * kept for the user (« Activité du compte ») and logged for the operator.
 */
interface AuditTrail
{
    /**
     * @param string                                    $action  stable, `context.what` (`nutrition.target_set`)
     * @param array<string, string|int|float|bool|null> $details what changed, never a secret
     * @param string                                    $source  who did it: `user`, `coach`, `system`
     */
    public function record(UserId $userId, string $action, array $details, string $source, \DateTimeImmutable $occurredOn): void;
}
