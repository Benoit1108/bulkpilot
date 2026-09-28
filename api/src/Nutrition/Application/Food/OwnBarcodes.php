<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Exception\BarcodeAlreadyUsed;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Domain\Identifier\UserId;

/**
 * A barcode names one of the user's foods at most, so that a scan is never ambiguous.
 */
final readonly class OwnBarcodes
{
    public function __construct(private FoodRepository $foods)
    {
    }

    /**
     * @throws BarcodeAlreadyUsed
     */
    public function assertFree(UserId $userId, ?Barcode $barcode, ?FoodId $except = null): void
    {
        if (null === $barcode) {
            return;
        }
        $food = $this->foods->findByBarcode($userId, $barcode);
        if (null !== $food && $food->isEditableBy($userId) && (null === $except || !$food->id()->equals($except))) {
            throw BarcodeAlreadyUsed::by($food->name());
        }
    }
}
