<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

/**
 * One part of the adherence: what was done of what was expected (the explanation, spec §58).
 */
final readonly class AdherenceComponent
{
    public function __construct(public AdherencePart $part, public int $done, public int $expected)
    {
    }

    /** 0 to 100; doing more than expected counts as all of it. */
    public function score(): int
    {
        return (int) round(min(1, $this->done / $this->expected) * 100);
    }
}
