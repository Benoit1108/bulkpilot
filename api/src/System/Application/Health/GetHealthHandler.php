<?php

declare(strict_types=1);

namespace App\System\Application\Health;

use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetHealthHandler implements QueryHandler
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(private iterable $checks)
    {
    }

    public function __invoke(GetHealth $query): HealthReport
    {
        $results = [];
        foreach ($this->checks as $check) {
            $results[$check->name()] = $check->isHealthy();
        }

        return new HealthReport($results);
    }
}
