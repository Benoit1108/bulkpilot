<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

use App\Shared\Domain\Identifier\UserId;

interface WeighInRepository
{
    public function save(WeighIn $weighIn): void;

    public function remove(WeighIn $weighIn): void;

    public function on(UserId $userId, \DateTimeImmutable $day): ?WeighIn;

    /** The first weigh-in ever: where the user started from. */
    public function first(UserId $userId): ?WeighIn;

    /**
     * @return list<WeighIn> from that day on (every one when null), the oldest first
     */
    public function since(UserId $userId, ?\DateTimeImmutable $day): array;

    /**
     * @return list<WeighIn> the most recent first, before a day (excluded) when given
     */
    public function latest(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array;
}
