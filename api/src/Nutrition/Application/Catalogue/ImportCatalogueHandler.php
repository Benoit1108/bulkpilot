<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Catalogue;

use App\Nutrition\Application\Port\FoodCatalogue;
use App\Nutrition\Domain\Exception\InvalidFood;
use App\Nutrition\Domain\Exception\InvalidNutritionFacts;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Domain\Identifier\UuidGenerator;

final readonly class ImportCatalogueHandler implements CommandHandler
{
    public function __construct(private FoodCatalogue $catalogue, private FoodRepository $foods, private UuidGenerator $uuids)
    {
    }

    public function __invoke(ImportCatalogue $command): void
    {
        $source = $this->catalogue->source();
        $existing = $this->foods->catalogue($source);
        foreach ($this->catalogue->read($command->location) as $entry) {
            try {
                $facts = self::factsOf($entry);
                $food = $existing[$entry->reference] ?? null;
                if (null === $food) {
                    $this->foods->save(Food::fromCatalogue(new FoodId($this->uuids->generate()), $source, $entry->reference, $entry->name, $entry->category, $facts));
                    ++$command->report->created;
                } else {
                    $food->refreshFromCatalogue($entry->name, $entry->category, $facts);
                    $this->foods->save($food);
                    ++$command->report->updated;
                }
            } catch (InvalidNutritionFacts|InvalidFood $e) {
                $command->report->skipped[$entry->reference] = $e->getMessage();
            }
        }
    }

    /**
     * Some foods have no energy value but all their macros: it is then computed with the EU
     * conversion factors (Regulation 1169/2011, annex XIV). A macro missing from a food that has
     * an energy value is taken as 0 (drinks, oils, sugar). Optional values that contradict the
     * macros (sugars above carbohydrate) are dropped rather than losing the food.
     */
    private static function factsOf(CatalogueEntry $entry): NutritionFacts
    {
        if (null === $entry->protein && null === $entry->carbohydrate && null === $entry->fat) {
            throw InvalidNutritionFacts::because('no macronutrient value');
        }
        $energy = $entry->energyKcal;
        if (null === $energy) {
            if (null === $entry->protein || null === $entry->carbohydrate || null === $entry->fat) {
                throw InvalidNutritionFacts::because('no energy value');
            }
            $energy = round(4 * $entry->protein + 4 * $entry->carbohydrate + 9 * $entry->fat + 2 * ($entry->fibre ?? 0.0));
        }
        $carbohydrate = $entry->carbohydrate ?? 0.0;
        $fat = $entry->fat ?? 0.0;

        return new NutritionFacts(
            $energy,
            $entry->protein ?? 0.0,
            $carbohydrate,
            $fat,
            $entry->fibre,
            null !== $entry->sugars && $entry->sugars <= $carbohydrate ? $entry->sugars : null,
            null !== $entry->saturatedFat && $entry->saturatedFat <= $fat ? $entry->saturatedFat : null,
            $entry->salt,
        );
    }
}
