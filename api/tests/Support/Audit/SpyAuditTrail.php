<?php

declare(strict_types=1);

namespace App\Tests\Support\Audit;

use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Domain\Identifier\UserId;

final class SpyAuditTrail implements AuditTrail
{
    /** @var list<array{user: string, action: string, details: array<string, string|int|float|bool|null>, source: string, at: string}> */
    public array $recorded = [];

    public function record(UserId $userId, string $action, array $details, string $source, \DateTimeImmutable $occurredOn): void
    {
        $this->recorded[] = ['user' => $userId->value, 'action' => $action, 'details' => $details, 'source' => $source, 'at' => $occurredOn->format(\DATE_ATOM)];
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_column($this->recorded, 'action');
    }
}
