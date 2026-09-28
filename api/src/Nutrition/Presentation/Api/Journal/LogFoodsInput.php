<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use App\Nutrition\Application\Journal\LogFoods;
use Symfony\Component\Validator\Constraints as Assert;

final class LogFoodsInput
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $day = '';

    #[Assert\Choice(choices: MealSlots::VALUES)]
    public string $slot = 'breakfast';

    /** @var list<LogFoodsItemInput> */
    #[Assert\Count(min: 1, max: LogFoods::MAX_ITEMS)]
    #[Assert\Valid]
    public array $items = [];
}
