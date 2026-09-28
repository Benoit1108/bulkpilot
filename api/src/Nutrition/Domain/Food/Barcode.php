<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

use App\Nutrition\Domain\Exception\InvalidBarcode;

/**
 * A product's GTIN as printed under the bars: EAN-8, UPC-A (12), EAN-13 or GTIN-14, with a valid
 * GS1 check digit. Stored in its shortest form (GS1 pads GTINs with leading zeros: UPC-A, and
 * GTIN-13/14 wrapping a shorter code, are brought back to it), so that one product has one code
 * whatever the scanner or the label reports.
 */
final readonly class Barcode
{
    /** What request payloads may carry before normalisation: digits, spaces, dashes. */
    public const string INPUT_PATTERN = '/^[\d\s-]{8,20}$/';

    public string $value;

    public function __construct(string $code)
    {
        $digits = preg_replace('/[\s-]/', '', $code) ?? '';
        if (1 !== preg_match('/^(\d{8}|\d{12,14})$/', $digits)) {
            throw InvalidBarcode::because('a barcode has 8, 12, 13 or 14 digits');
        }
        if ('' === trim($digits, '0')) {
            throw InvalidBarcode::because('a barcode is not only zeros');
        }
        if (!self::hasValidCheckDigit($digits)) {
            throw InvalidBarcode::because('the check digit does not match');
        }

        $this->value = self::shortest($digits);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * EAN-8 when padded with six zeros, EAN-13 when padded with one, otherwise the GTIN-14.
     */
    private static function shortest(string $digits): string
    {
        $gtin = str_pad($digits, 14, '0', \STR_PAD_LEFT);

        return match (true) {
            8 === \strlen($digits), str_starts_with($gtin, '000000') => substr($gtin, 6),
            str_starts_with($gtin, '0') => substr($gtin, 1),
            default => $gtin,
        };
    }

    /**
     * GS1: from the right, digits alternately weigh 3 and 1 (check digit excluded); the check
     * digit brings the sum to a multiple of 10. Leading zeros do not change it.
     */
    private static function hasValidCheckDigit(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $sum = 0;
        foreach (str_split(strrev($body)) as $position => $digit) {
            $sum += (int) $digit * (0 === $position % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($digits, -1);
    }
}
