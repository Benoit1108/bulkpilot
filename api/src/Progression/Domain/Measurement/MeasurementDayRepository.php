<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

use App\Shared\Domain\Identifier\UserId;

interface MeasurementDayRepository
{
    public function save(MeasurementDay $day): void;

    public function remove(MeasurementDay $day): void;

    public function on(UserId $userId, \DateTimeImmutable $day): ?MeasurementDay;

    /**
     * @return list<MeasurementDay> every day measured, the oldest first
     */
    public function ofUser(UserId $userId): array;
}
