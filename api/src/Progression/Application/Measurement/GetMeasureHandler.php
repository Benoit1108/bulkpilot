<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetMeasureHandler implements QueryHandler
{
    public function __construct(private CustomMeasureRepository $measures)
    {
    }

    public function __invoke(GetMeasure $query): MeasureView
    {
        return MeasureView::custom($this->measures->getOwnedBy($query->measureId, $query->userId));
    }
}
