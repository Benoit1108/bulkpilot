<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Shared\Domain\Exception\Unavailable;

final class ProductDatabaseUnavailable extends Unavailable
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'nutrition.product_database_unavailable';
    }
}
