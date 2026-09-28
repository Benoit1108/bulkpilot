<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use Symfony\Component\Validator\Constraints as Assert;

final class LogFavoriteInput
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $day = '';

    #[Assert\Choice(choices: MealSlots::VALUES)]
    public string $slot = 'breakfast';
}
