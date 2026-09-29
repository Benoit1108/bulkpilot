<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Exception\MeasurementsNotFound;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetMeasurementsHandler implements QueryHandler
{
    public function __construct(private MeasurementDayRepository $days)
    {
    }

    public function __invoke(GetMeasurements $query): MeasurementDayView
    {
        return MeasurementDayView::of($this->days->on($query->userId, $query->day) ?? throw MeasurementsNotFound::on($query->day));
    }
}
