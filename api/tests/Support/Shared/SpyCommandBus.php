<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Application\Bus\Command\CommandBus;

/**
 * Keeps what is dispatched instead of handling it: what a fan-out sends to the worker.
 */
final class SpyCommandBus implements CommandBus
{
    /** @var list<Command> */
    public array $dispatched = [];

    public function dispatch(Command $command): void
    {
        $this->dispatched[] = $command;
    }
}
