<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Domain\Exception\ProductNotFound;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetScannedProductHandler implements QueryHandler
{
    public function __construct(private FoodRepository $foods)
    {
    }

    /**
     * @throws ProductNotFound
     */
    public function __invoke(GetScannedProduct $query): FoodView
    {
        $food = $this->foods->findSharedByBarcode($query->barcode) ?? throw ProductNotFound::withBarcode($query->barcode->value);

        return FoodView::of($food, $query->userId);
    }
}
