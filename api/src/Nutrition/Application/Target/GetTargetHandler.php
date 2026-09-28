<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetTargetHandler implements QueryHandler
{
    public function __construct(private NutritionTargetRepository $targets)
    {
    }

    public function __invoke(GetTarget $query): TargetView
    {
        return TargetView::of($this->targets->getOwnedBy($query->targetId, $query->userId));
    }
}
