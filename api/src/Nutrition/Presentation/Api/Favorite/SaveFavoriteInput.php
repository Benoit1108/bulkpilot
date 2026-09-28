<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Favorite;

use App\Nutrition\Presentation\Api\Journal\MealSlots;
use Symfony\Component\Validator\Constraints as Assert;

final class SaveFavoriteInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 100, normalizer: 'trim')]
    public string $name = '';

    /** The meal to keep: its day… */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $day = '';

    /** …and its slot. */
    #[Assert\Choice(choices: MealSlots::VALUES)]
    public string $slot = 'breakfast';
}
