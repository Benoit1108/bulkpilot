<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Shared\Domain\Identifier\Uuid;

/**
 * Chosen by the client, so that a set sent twice (retry after a lost connection) is recorded once.
 */
final readonly class SetId extends Uuid
{
}
