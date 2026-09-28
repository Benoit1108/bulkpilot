<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Exception\ProductNotFound;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class KeepScannedProductHandler implements CommandHandler
{
    public function __construct(private FoodRepository $foods, private FoodDatabaseProvider $provider)
    {
    }

    /**
     * Two users confirming a new product at the same moment: the unique source reference refuses
     * the second insert, which the command bus reports as a concurrent update (409, retry).
     *
     * @throws ProductNotFound
     * @throws ProductDatabaseUnavailable
     * @throws IncompleteProduct          the values must be completed (they become the user's own food)
     */
    public function __invoke(KeepScannedProduct $command): void
    {
        $product = $this->provider->findByBarcode($command->barcode) ?? throw ProductNotFound::withBarcode($command->barcode->value);
        $proposal = ProductProposal::of($product);
        $facts = $proposal->facts();

        $shared = $this->foods->findSharedByBarcode($command->barcode);
        if (null !== $shared) {
            $shared->refreshFromProduct($proposal->name(), $product->brand, $product->category, $facts, $proposal->portions());
            $this->foods->save($shared);

            return;
        }

        $this->foods->save(Food::fromScannedProduct($command->foodId, $command->barcode, $proposal->name(), $product->brand, $product->category, $facts, $proposal->portions()));
    }
}
