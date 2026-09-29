<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ArchiveMeasureHandler implements CommandHandler
{
    public function __construct(private CustomMeasureRepository $measures, private ClockInterface $clock)
    {
    }

    public function __invoke(ArchiveMeasure $command): void
    {
        $measure = $this->measures->getOwnedBy($command->measureId, $command->userId);
        $measure->archive($this->clock->now());
        $this->measures->save($measure);
    }
}
