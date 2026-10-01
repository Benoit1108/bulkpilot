<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\Application\Bus\Command\Command;

/**
 * Deletes the archives of the exports past their time (scheduled).
 */
final readonly class PurgeExpiredExports implements Command
{
}
