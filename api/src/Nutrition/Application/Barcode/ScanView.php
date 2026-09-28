<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Domain\Food\Barcode;

/**
 * The answer to a scan: the food we already have for it, or what the external database proposes.
 */
final readonly class ScanView
{
    private function __construct(public Barcode $barcode, public ?FoodView $food, public ?ProductProposal $proposal)
    {
    }

    public static function known(Barcode $barcode, FoodView $food): self
    {
        return new self($barcode, $food, null);
    }

    public static function proposed(Barcode $barcode, ProductProposal $proposal): self
    {
        return new self($barcode, null, $proposal);
    }
}
