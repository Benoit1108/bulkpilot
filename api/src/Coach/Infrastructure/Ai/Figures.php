<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Ai;

use App\Coach\Domain\Rule\Finding;

/**
 * Typed reads of a finding's figures for the templates.
 *
 * @phpstan-import-type Figures from Finding as FindingFigures
 */
final readonly class Figures
{
    /**
     * @param FindingFigures $figures
     */
    public function __construct(private array $figures)
    {
    }

    public function float(string $key): float
    {
        $value = $this->figures[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : 0.0;
    }

    public function int(string $key): int
    {
        return (int) round($this->float($key));
    }

    public function nullableInt(string $key): ?int
    {
        return null === ($this->figures[$key] ?? null) ? null : $this->int($key);
    }

    /**
     * @return list<array{exercise: string, sessions: int, since: string}>
     */
    public function stalls(): array
    {
        $stalls = $this->figures['exercises'] ?? [];

        return \is_array($stalls) ? $stalls : [];
    }
}
