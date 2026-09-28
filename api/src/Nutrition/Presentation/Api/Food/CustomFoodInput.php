<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use App\Nutrition\Application\Food\CustomFoodDetails;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Values per 100 g, as printed on the label.
 */
final class CustomFoodInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: Food::MAX_NAME_LENGTH, normalizer: 'trim')]
    public string $name = '';

    #[Assert\Length(max: Food::MAX_BRAND_LENGTH, normalizer: 'trim')]
    public ?string $brand = null;

    #[Assert\Choice(choices: ['raw', 'cooked', 'not_applicable'])]
    public string $state = 'not_applicable';

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: NutritionFacts::MAX_ENERGY_KCAL)]
    public ?float $energyKcal = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    public ?float $protein = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    public ?float $carbohydrate = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    public ?float $fat = null;

    #[Assert\Range(min: 0, max: 100)]
    public ?float $fibre = null;

    #[Assert\Range(min: 0, max: 100)]
    public ?float $sugars = null;

    #[Assert\Range(min: 0, max: 100)]
    public ?float $saturatedFat = null;

    #[Assert\Range(min: 0, max: 100)]
    public ?float $salt = null;

    /** @var list<PortionInput> */
    #[Assert\Count(max: Food::MAX_PORTIONS)]
    #[Assert\Valid]
    public array $portions = [];

    public function details(): CustomFoodDetails
    {
        return new CustomFoodDetails(
            $this->name,
            $this->brand,
            FoodState::from($this->state),
            (float) $this->energyKcal,
            (float) $this->protein,
            (float) $this->carbohydrate,
            (float) $this->fat,
            $this->fibre,
            $this->sugars,
            $this->saturatedFat,
            $this->salt,
            array_map(static fn (PortionInput $portion): array => ['label' => $portion->label, 'grams' => (float) $portion->grams], $this->portions),
        );
    }
}
