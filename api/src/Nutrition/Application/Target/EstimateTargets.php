<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\EnergyEstimate;
use App\Shared\Application\Bus\Query\Query;

/**
 * Preview only: nothing is saved until the user accepts it (SetTargets).
 *
 * @implements Query<EnergyEstimate>
 */
final readonly class EstimateTargets implements Query
{
    public function __construct(public EstimateRequest $request)
    {
    }
}
