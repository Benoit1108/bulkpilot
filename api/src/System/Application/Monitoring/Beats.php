<?php

declare(strict_types=1);

namespace App\System\Application\Monitoring;

use App\System\Domain\Monitoring\Beat;

/**
 * Where the last beat of each kind is kept, readable by every container.
 */
interface Beats
{
    /** Writes at once (no transaction to wait for): a beat is a fact about now. */
    public function record(Beat $beat, \DateTimeImmutable $at): void;

    public function lastOf(Beat $beat): ?\DateTimeImmutable;

    /** The first beat ever recorded (kept through later ones). */
    public function firstOf(Beat $beat): ?\DateTimeImmutable;
}
