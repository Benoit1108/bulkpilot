<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<MeasureView>
 */
final readonly class GetMeasure implements Query
{
    public function __construct(public UserId $userId, public CustomMeasureId $measureId)
    {
    }
}
