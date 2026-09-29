<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The measures one can take: the body parts, then one's own (not archived).
 *
 * @implements Query<list<MeasureView>>
 */
final readonly class ListMeasures implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
