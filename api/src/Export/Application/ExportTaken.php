<?php

declare(strict_types=1);

namespace App\Export\Application;

/**
 * The export is not waiting any more (being made, made, failed, gone): nothing for this worker.
 */
final class ExportTaken extends \RuntimeException
{
}
