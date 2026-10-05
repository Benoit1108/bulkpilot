<?php

declare(strict_types=1);

namespace App\System\Application\Health;

use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetHealthHandler implements QueryHandler
{
    /**
     * @param iterable<HealthCheck> $checks
     * @param iterable<Monitor>     $monitors
     */
    public function __construct(private iterable $checks, private iterable $monitors = [])
    {
    }

    public function __invoke(GetHealth $query): HealthReport
    {
        $results = [];
        foreach ($this->checks as $check) {
            $results[$check->name()] = $check->isHealthy();
        }

        $statuses = [];
        if ($query->monitoring) {
            foreach ($this->monitors as $monitor) {
                $statuses = [...$statuses, ...$monitor->statuses()];
            }
        }

        return new HealthReport($results, $statuses);
    }
}
