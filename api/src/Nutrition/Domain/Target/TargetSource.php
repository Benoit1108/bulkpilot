<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

enum TargetSource: string
{
    /** Accepted from the EnergyCalculator estimate. */
    case Estimate = 'estimate';
    /** Typed by the user. */
    case Manual = 'manual';
    /** A Coach recommendation the user accepted (phase 6). */
    case Coach = 'coach';
}
