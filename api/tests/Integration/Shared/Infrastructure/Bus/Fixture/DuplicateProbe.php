<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Command\Command;

/**
 * Inserts the same primary key twice: what two racing requests look like to the database.
 */
final readonly class DuplicateProbe implements Command
{
}
