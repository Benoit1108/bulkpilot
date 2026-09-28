<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Application\Bus\Command\Command;

/**
 * The user confirms a scanned product as Open Food Facts gives it: it joins (or refreshes) the
 * shared catalogue. The values are read again from the database, never taken from the client.
 * Corrected values go through CreateCustomFood / EditCustomFood instead (the user's own food).
 */
final readonly class KeepScannedProduct implements Command
{
    /**
     * @param FoodId $foodId used when the product is not in the catalogue yet
     */
    public function __construct(public FoodId $foodId, public Barcode $barcode)
    {
    }
}
