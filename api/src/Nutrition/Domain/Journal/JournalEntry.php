<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

use App\Nutrition\Domain\Exception\InvalidQuantity;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * One line of the food journal: a quantity of a food, in a meal of a day.
 * The day is the user's local calendar day, chosen by the client.
 */
class JournalEntry extends AggregateRoot
{
    public const float MAX_GRAMS = 5000.0;

    private function __construct(
        private readonly JournalEntryId $id,
        private readonly UserId $ownerId,
        private \DateTimeImmutable $day,
        private MealSlot $slot,
        private readonly LoggedFood $food,
        private float $grams,
        private readonly \DateTimeImmutable $loggedAt,
    ) {
    }

    public static function log(JournalEntryId $id, UserId $owner, \DateTimeImmutable $day, MealSlot $slot, LoggedFood $food, float $grams, \DateTimeImmutable $now): self
    {
        return new self($id, $owner, self::dayOf($day), $slot, $food, self::validGrams($grams), $now);
    }

    public function correct(float $grams, MealSlot $slot, \DateTimeImmutable $day): void
    {
        $this->grams = self::validGrams($grams);
        $this->slot = $slot;
        $this->day = self::dayOf($day);
    }

    public function amounts(): NutrientAmounts
    {
        return $this->food->per100Grams->scale($this->grams / 100);
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function id(): JournalEntryId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function day(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function slot(): MealSlot
    {
        return $this->slot;
    }

    public function food(): LoggedFood
    {
        return $this->food;
    }

    public function grams(): float
    {
        return $this->grams;
    }

    public function loggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }

    private static function validGrams(float $grams): float
    {
        if ($grams <= 0 || $grams > self::MAX_GRAMS) {
            throw InvalidQuantity::because(\sprintf('a quantity is between 0 and %d g', (int) self::MAX_GRAMS));
        }

        return round($grams, 1);
    }

    private static function dayOf(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(0, 0);
    }
}
