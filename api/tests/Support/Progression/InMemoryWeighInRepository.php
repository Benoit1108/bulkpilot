<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryWeighInRepository implements WeighInRepository
{
    /** @var array<string, WeighIn> by owner and day */
    public array $weighIns = [];

    public function save(WeighIn $weighIn): void
    {
        $this->weighIns[self::key($weighIn->ownerId(), $weighIn->day())] = $weighIn;
    }

    public function remove(WeighIn $weighIn): void
    {
        unset($this->weighIns[self::key($weighIn->ownerId(), $weighIn->day())]);
    }

    public function on(UserId $userId, \DateTimeImmutable $day): ?WeighIn
    {
        return $this->weighIns[self::key($userId, $day)] ?? null;
    }

    public function first(UserId $userId): ?WeighIn
    {
        return $this->since($userId, null)[0] ?? null;
    }

    public function since(UserId $userId, ?\DateTimeImmutable $day): array
    {
        $weighIns = array_values(array_filter(
            $this->weighIns,
            static fn (WeighIn $weighIn): bool => $weighIn->ownerId()->equals($userId) && (null === $day || $weighIn->day() >= $day),
        ));
        usort($weighIns, static fn (WeighIn $a, WeighIn $b): int => $a->day() <=> $b->day());

        return $weighIns;
    }

    public function latest(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array
    {
        $weighIns = array_reverse(array_values(array_filter($this->since($userId, null), static fn (WeighIn $weighIn): bool => null === $before || $weighIn->day() < $before)));

        return \array_slice($weighIns, 0, $limit);
    }

    private static function key(UserId $userId, \DateTimeImmutable $day): string
    {
        return $userId->value.'|'.$day->format('Y-m-d');
    }
}
