<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use App\Nutrition\Domain\Journal\JournalEntry;
use Symfony\Component\Validator\Constraints as Assert;

final class CorrectEntryInput
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $day = '';

    #[Assert\Choice(choices: MealSlots::VALUES)]
    public string $slot = 'breakfast';

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(JournalEntry::MAX_GRAMS)]
    public ?float $grams = null;
}
