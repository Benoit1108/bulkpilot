<?php

declare(strict_types=1);

namespace App\Tests\Support\System;

use App\System\Application\Monitoring\Pings;
use App\System\Domain\Monitoring\Beat;

final class RecordingPings implements Pings
{
    /** @var list<string> "backup up", "backup down: reason" */
    public array $sent = [];

    public function last(): ?string
    {
        return [] === $this->sent ? null : $this->sent[\count($this->sent) - 1];
    }

    public function up(Beat $beat): void
    {
        $this->sent[] = $beat->value.' up';
    }

    public function down(Beat $beat, string $reason): void
    {
        $this->sent[] = $beat->value.' down: '.$reason;
    }
}
