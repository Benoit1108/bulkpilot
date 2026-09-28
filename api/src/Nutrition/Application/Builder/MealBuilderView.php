<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Builder;

use App\Nutrition\Domain\Builder\MacroGoal;
use App\Nutrition\Domain\Journal\MealSlot;

final readonly class MealBuilderView
{
    /**
     * @param MacroGoal|null     $goal      null without a target for that day
     * @param MealSlot|null      $slot      the meal the goal is for; null for the rest of the day
     * @param list<ProposalView> $proposals
     */
    public function __construct(public ?MacroGoal $goal, public ?MealSlot $slot, public array $proposals)
    {
    }
}
