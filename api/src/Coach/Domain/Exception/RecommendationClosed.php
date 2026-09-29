<?php

declare(strict_types=1);

namespace App\Coach\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

final class RecommendationClosed extends Conflict
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'coach.recommendation_closed';
    }
}
