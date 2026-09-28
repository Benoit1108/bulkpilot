<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

/**
 * Owner of every personal record. Lives in the shared kernel so that each bounded context can
 * reference the user without depending on the Identity context (ADR-0015).
 */
final readonly class UserId extends Uuid
{
}
