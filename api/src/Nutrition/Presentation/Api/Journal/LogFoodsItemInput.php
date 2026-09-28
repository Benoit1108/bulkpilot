<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use App\Nutrition\Domain\Journal\JournalEntry;
use Symfony\Component\Validator\Constraints as Assert;

final class LogFoodsItemInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $foodId = '';

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(JournalEntry::MAX_GRAMS)]
    public ?float $grams = null;
}
