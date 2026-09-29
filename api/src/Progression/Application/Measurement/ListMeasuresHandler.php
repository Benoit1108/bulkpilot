<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\BodyPart;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListMeasuresHandler implements QueryHandler
{
    public function __construct(private CustomMeasureRepository $measures)
    {
    }

    /**
     * @return list<MeasureView>
     */
    public function __invoke(ListMeasures $query): array
    {
        return [
            ...array_map(MeasureView::part(...), BodyPart::cases()),
            ...array_map(MeasureView::custom(...), $this->measures->ofUser($query->userId)),
        ];
    }
}
