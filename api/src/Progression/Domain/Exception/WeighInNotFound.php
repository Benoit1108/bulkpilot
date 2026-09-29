<?php

declare(strict_types=1);

namespace App\Progression\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class WeighInNotFound extends NotFound
{
    public static function on(\DateTimeImmutable $day): self
    {
        return new self(\sprintf('No weigh-in on %s.', $day->format('Y-m-d')));
    }

    public function errorCode(): string
    {
        return 'progression.weigh_in_not_found';
    }
}
