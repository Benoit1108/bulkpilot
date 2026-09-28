<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Barcode;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Barcode\ProductProposal;

/**
 * What Open Food Facts says about a product, to confirm or correct. Values are per 100 g; null
 * when the database does not give them.
 */
final readonly class ScannedProduct
{
    /**
     * @param list<string> $missing
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public ?string $name,
        #[ApiProperty(required: true)]
        public ?string $brand,
        #[ApiProperty(required: true)]
        public ?string $quantity,
        #[ApiProperty(required: true)]
        public ?float $energyKcal,
        #[ApiProperty(required: true)]
        public ?float $protein,
        #[ApiProperty(required: true)]
        public ?float $carbohydrate,
        #[ApiProperty(required: true)]
        public ?float $fat,
        #[ApiProperty(required: true)]
        public ?float $fibre,
        #[ApiProperty(required: true)]
        public ?float $sugars,
        #[ApiProperty(required: true)]
        public ?float $saturatedFat,
        #[ApiProperty(required: true)]
        public ?float $salt,
        #[ApiProperty(required: true)]
        public ?float $servingGrams,
        /** Required values the database does not give (name, energyKcal, protein, carbohydrate, fat). */
        #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['name', 'energyKcal', 'protein', 'carbohydrate', 'fat']]])]
        public array $missing,
        /** The value that makes the others implausible (for example sugars above carbohydrate; macros when protein, carbohydrate and fat add up to more than 100 g), when all are given. */
        #[ApiProperty(required: true, schema: ['type' => ['string', 'null'], 'enum' => ['energyKcal', 'protein', 'carbohydrate', 'fat', 'fibre', 'sugars', 'saturatedFat', 'salt', 'macros', null]])]
        public ?string $suspect,
        /** True when it can be confirmed as is; otherwise it has to be completed. */
        #[ApiProperty(required: true)]
        public bool $complete,
    ) {
    }

    public static function of(ProductProposal $proposal): self
    {
        $product = $proposal->product;

        return new self(
            $product->name,
            $product->brand,
            $product->quantity,
            $product->energyKcal,
            $product->protein,
            $product->carbohydrate,
            $product->fat,
            $product->fibre,
            $product->sugars,
            $product->saturatedFat,
            $product->salt,
            $product->servingGrams,
            $proposal->missing,
            $proposal->suspect,
            $proposal->isComplete(),
        );
    }
}
