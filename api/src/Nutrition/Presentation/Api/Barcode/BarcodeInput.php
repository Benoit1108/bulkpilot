<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Barcode;

use App\Nutrition\Domain\Food\Barcode;
use Symfony\Component\Validator\Constraints as Assert;

final class BarcodeInput
{
    /** EAN-8, UPC-A, EAN-13 or GTIN-14, as read under the bars. */
    #[Assert\NotBlank]
    #[Assert\Regex(Barcode::INPUT_PATTERN)]
    public string $barcode = '';
}
