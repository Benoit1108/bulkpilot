<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Command;

/**
 * Marker for use cases that change state. Implementations expose a single
 * `__invoke(SomeCommand $command): void` method and are wired to the command bus
 * automatically (see config/services.yaml), without any framework attribute.
 */
interface CommandHandler
{
}
