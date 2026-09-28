<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class ProductNotFound extends NotFound
{
    public static function withBarcode(string $barcode): self
    {
        return new self(\sprintf('No product with barcode "%s".', $barcode));
    }

    public function errorCode(): string
    {
        return 'nutrition.product_not_found';
    }
}
