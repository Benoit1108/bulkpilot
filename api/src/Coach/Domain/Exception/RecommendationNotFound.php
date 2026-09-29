<?php

declare(strict_types=1);

namespace App\Coach\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class RecommendationNotFound extends NotFound
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'coach.recommendation_not_found';
    }
}
