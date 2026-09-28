<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class EditCustomFoodHandler implements CommandHandler
{
    public function __construct(private FoodRepository $foods, private OwnBarcodes $barcodes)
    {
    }

    public function __invoke(EditCustomFood $command): void
    {
        $food = $this->foods->getVisibleTo($command->foodId, $command->userId);
        $details = $command->details;
        $barcode = $details->barcode();
        $this->barcodes->assertFree($command->userId, $barcode, $food->id());
        $food->edit($details->name, $details->brand, $details->state, $details->facts(), $details->portions(), $barcode);
        $this->foods->save($food);
    }
}
