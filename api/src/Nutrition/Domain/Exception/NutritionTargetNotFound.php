<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class NutritionTargetNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Nutrition target "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'nutrition.target_not_found';
    }
}
