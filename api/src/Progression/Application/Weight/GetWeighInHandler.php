<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Exception\WeighInNotFound;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetWeighInHandler implements QueryHandler
{
    public function __construct(private WeighInRepository $weighIns)
    {
    }

    public function __invoke(GetWeighIn $query): WeighInView
    {
        $weighIn = $this->weighIns->on($query->userId, $query->day) ?? throw WeighInNotFound::on($query->day);

        return WeighInView::of($weighIn);
    }
}
