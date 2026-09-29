<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasure;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Progression\Domain\Measurement\Length;
use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Progression\Domain\Measurement\Measures;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class RecordMeasurementsHandler implements CommandHandler
{
    public function __construct(private MeasurementDayRepository $days, private CustomMeasureRepository $measures, private ClockInterface $clock)
    {
    }

    public function __invoke(RecordMeasurements $command): void
    {
        $all = $this->measures->ofUser($command->userId, withArchived: true);
        $lengths = new Measures($all)->lengths($command->centimetres);
        $now = $this->clock->now();
        $day = $this->days->on($command->userId, $command->day);
        if (null === $day) {
            $day = MeasurementDay::record($command->dayId, $command->userId, $command->day, $lengths, $now);
        } else {
            // An archived measure leaves the form, not the days: its value there is kept.
            $archived = array_map(static fn (CustomMeasure $measure): string => $measure->key(), array_filter($all, static fn (CustomMeasure $measure): bool => $measure->isArchived()));
            $kept = array_map(static fn (float $cm): Length => new Length($cm), array_intersect_key($day->values(), array_flip($archived)));
            $day->replace($lengths + $kept, $now);
        }
        $this->days->save($day);
    }
}
