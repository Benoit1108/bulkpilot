<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Job;

interface JobBus
{
    /**
     * Runs the job now, or in the worker when its message is routed to the async transport.
     */
    public function dispatch(Job $job): void;
}
