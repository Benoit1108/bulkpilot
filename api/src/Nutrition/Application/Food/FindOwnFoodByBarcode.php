<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\Barcode;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The user's own (not deleted) food carrying this code, if any: correcting a scanned product
 * twice updates it rather than adding a second one.
 *
 * @implements Query<FoodView|null>
 */
final readonly class FindOwnFoodByBarcode implements Query
{
    public function __construct(public UserId $userId, public Barcode $barcode)
    {
    }
}
