<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class FindOwnFoodByBarcodeHandler implements QueryHandler
{
    public function __construct(private FoodRepository $foods)
    {
    }

    public function __invoke(FindOwnFoodByBarcode $query): ?FoodView
    {
        $food = $this->foods->findByBarcode($query->userId, $query->barcode);

        return null !== $food && $food->isEditableBy($query->userId) ? FoodView::of($food, $query->userId) : null;
    }
}
