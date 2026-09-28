<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Domain\Exception\NutritionTargetNotFound;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryNutritionTargetRepository implements NutritionTargetRepository
{
    /** @var list<NutritionTarget> */
    public array $targets = [];

    public function save(NutritionTarget $target): void
    {
        $this->targets[] = $target;
    }

    public function getOwnedBy(NutritionTargetId $id, UserId $userId): NutritionTarget
    {
        foreach ($this->targets as $target) {
            if ($target->id()->equals($id) && $target->ownerId()->equals($userId)) {
                return $target;
            }
        }

        throw NutritionTargetNotFound::withId($id->value);
    }

    public function inForceOn(UserId $userId, \DateTimeImmutable $day): ?NutritionTarget
    {
        foreach ($this->history($userId, \PHP_INT_MAX) as $target) {
            if ($target->effectiveFrom() <= $day) {
                return $target;
            }
        }

        return null;
    }

    public function history(UserId $userId, int $limit): array
    {
        $targets = array_values(array_filter($this->targets, static fn (NutritionTarget $target): bool => $target->ownerId()->equals($userId)));
        usort($targets, static fn (NutritionTarget $a, NutritionTarget $b): int => [$b->setAt(), $b->id()->value] <=> [$a->setAt(), $a->id()->value]);

        return \array_slice($targets, 0, $limit);
    }
}
