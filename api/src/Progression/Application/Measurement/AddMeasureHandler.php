<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Exception\InvalidMeasurement;
use App\Progression\Domain\Measurement\CustomMeasure;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class AddMeasureHandler implements CommandHandler
{
    /** Enough for any body; bounds what one account can store, archived measures included. */
    public const int MAX_MEASURES = 20;
    public const int MAX_EVER = 60;

    public function __construct(private CustomMeasureRepository $measures, private ClockInterface $clock)
    {
    }

    public function __invoke(AddMeasure $command): void
    {
        $measure = CustomMeasure::add($command->measureId, $command->userId, $command->name, $this->clock->now());
        $all = $this->measures->ofUser($command->userId, withArchived: true);
        $taken = array_filter($all, static fn (CustomMeasure $one): bool => !$one->isArchived());
        if (\count($taken) >= self::MAX_MEASURES || \count($all) >= self::MAX_EVER) {
            throw InvalidMeasurement::because(\sprintf('at most %d measures of one\'s own', self::MAX_MEASURES));
        }
        // Archived ones too: their values still show under their name.
        MeasureNames::assertFree($measure->name(), $all);
        $this->measures->save($measure);
    }
}
