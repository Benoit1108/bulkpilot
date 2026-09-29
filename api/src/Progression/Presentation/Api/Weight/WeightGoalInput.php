<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Weight\Weight;
use Symfony\Component\Validator\Constraints as Assert;

final class WeightGoalInput
{
    #[Assert\NotNull]
    #[Assert\Range(min: Weight::MIN_KG, max: Weight::MAX_KG)]
    public ?float $targetKg = null;

    /** @var list<float> weights on the way to the target, in any order */
    #[Assert\Count(max: WeightGoal::MAX_MILESTONES)]
    #[Assert\All([new Assert\NotNull(), new Assert\Range(min: Weight::MIN_KG, max: Weight::MAX_KG)])]
    public array $milestonesKg = [];
}
