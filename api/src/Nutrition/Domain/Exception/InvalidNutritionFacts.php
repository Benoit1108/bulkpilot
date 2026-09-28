<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidNutritionFacts extends DomainException
{
    /**
     * @param string|null $field the value at fault (energyKcal, protein, carbohydrate, fat, fibre, sugars, saturatedFat, salt, or macros for their sum), when one is
     */
    private function __construct(string $reason, public readonly ?string $field)
    {
        parent::__construct($reason);
    }

    public static function because(string $reason, ?string $field = null): self
    {
        return new self($reason, $field);
    }

    public function errorCode(): string
    {
        return 'nutrition.invalid_nutrition_facts';
    }
}
