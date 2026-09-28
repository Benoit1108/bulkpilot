<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Domain\Food\Barcode;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * What a scanned code stands for. Saves nothing (spec §20: never add a product silently).
 *
 * @implements Query<ScanView>
 */
final readonly class ScanBarcode implements Query
{
    public function __construct(public UserId $userId, public Barcode $barcode)
    {
    }
}
