<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

enum TrendDirection: string
{
    case Gaining = 'gaining';
    case Losing = 'losing';
    case Stable = 'stable';
}
