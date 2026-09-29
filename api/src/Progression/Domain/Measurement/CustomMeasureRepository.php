<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

use App\Progression\Domain\Exception\MeasureNotFound;
use App\Shared\Domain\Identifier\UserId;

interface CustomMeasureRepository
{
    public function save(CustomMeasure $measure): void;

    /**
     * @throws MeasureNotFound also when it belongs to someone else
     */
    public function getOwnedBy(CustomMeasureId $id, UserId $userId): CustomMeasure;

    /**
     * @return list<CustomMeasure> in the order they were added, archived ones included when asked
     */
    public function ofUser(UserId $userId, bool $withArchived = false): array;
}
