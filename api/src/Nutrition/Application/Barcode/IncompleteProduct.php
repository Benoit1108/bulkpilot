<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Shared\Domain\Exception\DomainException;

/**
 * An external product cannot be kept as is: values are missing or implausible. The user can
 * complete it (it then becomes their own food).
 */
final class IncompleteProduct extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'nutrition.incomplete_product';
    }
}
