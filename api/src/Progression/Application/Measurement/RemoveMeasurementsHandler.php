<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class RemoveMeasurementsHandler implements CommandHandler
{
    public function __construct(private MeasurementDayRepository $days)
    {
    }

    public function __invoke(RemoveMeasurements $command): void
    {
        $day = $this->days->on($command->userId, $command->day);
        if (null !== $day) {
            $this->days->remove($day);
        }
    }
}
