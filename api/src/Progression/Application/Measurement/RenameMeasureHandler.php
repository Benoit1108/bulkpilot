<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class RenameMeasureHandler implements CommandHandler
{
    public function __construct(private CustomMeasureRepository $measures)
    {
    }

    public function __invoke(RenameMeasure $command): void
    {
        $measure = $this->measures->getOwnedBy($command->measureId, $command->userId);
        $measure->rename($command->name);
        MeasureNames::assertFree($measure->name(), $this->measures->ofUser($command->userId, withArchived: true), $measure);
        $this->measures->save($measure);
    }
}
