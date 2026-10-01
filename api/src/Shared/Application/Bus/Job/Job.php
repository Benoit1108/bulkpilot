<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Job;

/**
 * Long work outside any transaction (an export read from every context, a database dump): its
 * handler dispatches short commands for what it writes, so that neither a failed read aborts a
 * transaction nor a transaction stays open for minutes.
 */
interface Job
{
}
