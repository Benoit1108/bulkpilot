<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Domain\Food\Barcode;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The shared catalogue's product for a code, once confirmed.
 *
 * @implements Query<FoodView>
 */
final readonly class GetScannedProduct implements Query
{
    public function __construct(public UserId $userId, public Barcode $barcode)
    {
    }
}
