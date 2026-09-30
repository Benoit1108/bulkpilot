<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

/**
 * A deterministic rule: the same situation always gives the same drafts.
 */
interface Rule
{
    /**
     * @return list<Draft>
     */
    public function evaluate(Situation $situation): array;
}
