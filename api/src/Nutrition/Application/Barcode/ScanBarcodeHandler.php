<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Exception\ProductNotFound;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ScanBarcodeHandler implements QueryHandler
{
    public function __construct(private FoodRepository $foods, private FoodDatabaseProvider $provider)
    {
    }

    /**
     * @throws ProductNotFound            neither known here nor by the external database
     * @throws ProductDatabaseUnavailable
     */
    public function __invoke(ScanBarcode $query): ScanView
    {
        $food = $this->foods->findByBarcode($query->userId, $query->barcode);
        if (null !== $food) {
            return ScanView::known($query->barcode, FoodView::of($food, $query->userId));
        }

        $product = $this->provider->findByBarcode($query->barcode) ?? throw ProductNotFound::withBarcode($query->barcode->value);

        return ScanView::proposed($query->barcode, ProductProposal::of($product));
    }
}
