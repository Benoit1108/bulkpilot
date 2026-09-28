<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Nutrition\Domain\Exception\InvalidMealSplit;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Domain\Identifier\UserId;

/**
 * How the day's calories are spread over meals (spec §27), in whole percents summing to 100.
 * A slot at 0 % is not planned (it can still be logged).
 */
class MealSplit
{
    private int $version = 1;

    /** @var array<string, int> */
    private array $shares;

    /**
     * @param array<string, int> $shares by MealSlot value; an unknown slot is refused
     */
    private function __construct(private readonly UserId $ownerId, array $shares)
    {
        $this->shares = self::valid($shares);
    }

    public static function defaultFor(UserId $owner): self
    {
        return new self($owner, ['breakfast' => 25, 'lunch' => 30, 'snack' => 0, 'gainer' => 15, 'dinner' => 30]);
    }

    /**
     * @param array<string, int> $shares by MealSlot value; a missing slot is 0 %
     */
    public static function of(UserId $owner, array $shares): self
    {
        return new self($owner, $shares);
    }

    /**
     * @param array<string, int> $shares
     */
    public function change(array $shares): void
    {
        $this->shares = self::valid($shares);
    }

    /**
     * The part of the daily targets planned for a meal.
     */
    public function targetOf(MacroTargets $targets, MealSlot $slot): NutrientAmounts
    {
        return $targets->amounts()->scale($this->shareOf($slot) / 100);
    }

    public function shareOf(MealSlot $slot): int
    {
        return $this->shares[$slot->value] ?? 0;
    }

    /**
     * @return array<value-of<MealSlot>, int> in chronological slot order
     */
    public function shares(): array
    {
        $ordered = [];
        foreach (MealSlot::cases() as $slot) {
            $ordered[$slot->value] = $this->shareOf($slot);
        }

        return $ordered;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    /**
     * @param array<string, int> $shares
     *
     * @return array<string, int>
     */
    private static function valid(array $shares): array
    {
        $valid = [];
        foreach (MealSlot::cases() as $slot) {
            $share = $shares[$slot->value] ?? 0;
            if ($share < 0 || $share > 100) {
                throw InvalidMealSplit::because('each share is between 0 and 100 %');
            }
            $valid[$slot->value] = $share;
        }
        if (\count($shares) !== \count(array_intersect_key($shares, $valid))) {
            throw InvalidMealSplit::because('unknown meal slot');
        }
        if (100 !== array_sum($valid)) {
            throw InvalidMealSplit::because('shares must add up to 100 %');
        }

        return $valid;
    }
}
