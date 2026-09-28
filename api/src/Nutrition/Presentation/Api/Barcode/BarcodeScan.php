<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Barcode;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Nutrition\Application\Barcode\ScanView;
use App\Nutrition\Presentation\Api\Food\Food;

/**
 * Spec §20: scan → Symfony → product database → normalised proposal → user confirmation. A scan
 * saves nothing; POST /nutrition/barcode/confirm does, once the user agreed.
 */
#[ApiResource(
    shortName: 'BarcodeScan',
    operations: [
        new Post(
            uriTemplate: '/nutrition/barcode/scan',
            status: 200,
            description: 'What a barcode stands for: a food already here (known), or what Open Food Facts proposes (found). 404 when nobody knows it, 503 when Open Food Facts cannot be reached.',
            input: BarcodeInput::class,
            processor: BarcodeScanProcessor::class,
            name: 'barcode_scan',
        ),
    ],
)]
final readonly class BarcodeScan
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $barcode,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['known', 'found']])]
        public string $status,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public ?Food $food,
        #[ApiProperty(required: true)]
        public ?ScannedProduct $product,
    ) {
    }

    public static function fromView(ScanView $view): self
    {
        return new self(
            $view->barcode->value,
            null === $view->food ? 'found' : 'known',
            null === $view->food ? null : Food::fromView($view->food),
            null === $view->proposal ? null : ScannedProduct::of($view->proposal),
        );
    }
}
