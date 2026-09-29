<?php

declare(strict_types=1);

namespace App\Progression\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class MeasurementsNotFound extends NotFound
{
    public static function on(\DateTimeImmutable $day): self
    {
        return new self(\sprintf('No measurements on %s.', $day->format('Y-m-d')));
    }

    public function errorCode(): string
    {
        return 'progression.measurements_not_found';
    }
}
