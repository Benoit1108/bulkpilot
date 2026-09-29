<?php

declare(strict_types=1);

namespace App\Progression\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class MeasureNotFound extends NotFound
{
    public static function withKey(string $key): self
    {
        return new self(\sprintf('Measure "%s" not found.', $key));
    }

    public function errorCode(): string
    {
        return 'progression.measure_not_found';
    }
}
