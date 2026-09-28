<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Command;

interface CommandBus
{
    /**
     * Runs the command synchronously inside a database transaction.
     */
    public function dispatch(Command $command): void;
}
