<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Identifier\Uuid;

final readonly class AuditEventId extends Uuid
{
}
