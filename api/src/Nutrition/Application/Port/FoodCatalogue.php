<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Port;

use App\Nutrition\Application\Catalogue\CatalogueEntry;
use App\Nutrition\Domain\Food\FoodSource;

/**
 * A reference food table (CIQUAL for now), read from a local copy or its official download.
 */
interface FoodCatalogue
{
    public function source(): FoodSource;

    /**
     * @param string|null $location directory of the extracted files, URL of an archive, or null for the official download
     *
     * @return iterable<CatalogueEntry>
     */
    public function read(?string $location): iterable;
}
