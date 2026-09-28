<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

final class BarcodeAlreadyUsed extends Conflict
{
    public static function by(string $foodName): self
    {
        return new self(\sprintf('The barcode already names "%s".', $foodName));
    }

    public function errorCode(): string
    {
        return 'nutrition.barcode_already_used';
    }
}
