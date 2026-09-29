<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;

/**
 * One deterministic rule of the Coach (spec §64): the same facts always give the same finding.
 */
interface Rule
{
    public function evaluate(Facts $facts): ?Finding;
}
