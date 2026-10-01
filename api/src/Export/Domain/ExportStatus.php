<?php

declare(strict_types=1);

namespace App\Export\Domain;

enum ExportStatus: string
{
    /** Waiting for the worker. */
    case Queued = 'queued';
    /** The worker is writing it. */
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    /** Its archive was deleted, a day after it was ready. */
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return self::Queued === $this || self::Processing === $this;
    }
}
