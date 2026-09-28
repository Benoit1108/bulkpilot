<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\FoodDatabase;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\ProductDatabaseUnavailable;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Food\Barcode;

/**
 * A handful of products for development, tests and E2E, without calling Open Food Facts
 * (ADR-0007). FOOD_DATABASE=fake selects it.
 */
final class FakeFoodDatabaseProvider implements FoodDatabaseProvider
{
    public const string SPREAD = '3017620422003';
    public const string SODA = '5449000000996';
    /** Known, but without its macros: the user must complete it. */
    public const string INCOMPLETE = '2000000000015';
    /** Known, with values that cannot be right (more than 100 g of macros in 100 g). */
    public const string IMPLAUSIBLE = '2000000000022';
    /** The database is down for this one. */
    public const string UNAVAILABLE = '2000000000039';
    /**
     * Any code of this in-store range (GS1 prefix 29) is a complete product made up from its
     * digits: E2E tests scan a new product on each run, although confirmed products are shared.
     */
    public const string GENERATED_PREFIX = '29';

    public function findByBarcode(Barcode $barcode): ?ExternalProduct
    {
        return match ($barcode->value) {
            self::SPREAD => new ExternalProduct($barcode, 'Pâte à tartiner aux noisettes et au cacao', 'Nutella', 'Pâtes à tartiner au chocolat', 539, 6.3, 57.5, 30.9, null, 56.3, 10.6, 0.107, 15, '400 g'),
            self::SODA => new ExternalProduct($barcode, 'Coca-Cola', 'Coca-Cola', 'Sodas au cola', 42, 0, 10.6, 0, null, 10.6, 0, 0, 330, '33 cl'),
            self::INCOMPLETE => new ExternalProduct($barcode, 'Barre protéinée', 'Marque inconnue', null, 350, null, null, null, quantity: '60 g'),
            self::IMPLAUSIBLE => new ExternalProduct($barcode, 'Produit mal saisi', null, null, 400, 60, 60, 20),
            self::UNAVAILABLE => throw ProductDatabaseUnavailable::because('fake outage'),
            default => str_starts_with($barcode->value, self::GENERATED_PREFIX) && 13 === \strlen($barcode->value)
                ? new ExternalProduct($barcode, 'Produit test '.$barcode->value, 'Marque test', null, 250, 10, 30, 10, servingGrams: 40, quantity: '200 g')
                : null,
        };
    }
}
