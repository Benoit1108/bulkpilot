<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Several foods logged in one meal at once (a Meal Builder proposal), all or nothing.
 */
final readonly class LogFoods implements Command
{
    public const int MAX_ITEMS = 10;

    /**
     * @param list<LogItem> $items
     */
    public function __construct(public UserId $userId, public \DateTimeImmutable $day, public MealSlot $slot, public array $items)
    {
    }
}
