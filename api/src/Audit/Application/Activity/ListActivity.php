<?php

declare(strict_types=1);

namespace App\Audit\Application\Activity;

use App\Audit\Domain\AuditEventId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * « Activité du compte »: what happened to the account, the latest first, a page at a time.
 *
 * @implements Query<list<ActivityView>>
 */
final readonly class ListActivity implements Query
{
    public function __construct(public UserId $userId, public int $limit = 20, public ?\DateTimeImmutable $before = null, public ?AuditEventId $beforeId = null)
    {
    }
}
