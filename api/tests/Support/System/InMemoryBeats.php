<?php

declare(strict_types=1);

namespace App\Tests\Support\System;

use App\System\Application\Monitoring\Beats;
use App\System\Domain\Monitoring\Beat;

final class InMemoryBeats implements Beats
{
    /** @var array<value-of<Beat>, \DateTimeImmutable> */
    public array $last = [];

    /** @var array<value-of<Beat>, \DateTimeImmutable> */
    public array $first = [];

    public function record(Beat $beat, \DateTimeImmutable $at): void
    {
        $this->last[$beat->value] = $at;
        $this->first[$beat->value] ??= $at;
    }

    public function firstOf(Beat $beat): ?\DateTimeImmutable
    {
        return $this->first[$beat->value] ?? null;
    }

    public function lastOf(Beat $beat): ?\DateTimeImmutable
    {
        return $this->last[$beat->value] ?? null;
    }
}
