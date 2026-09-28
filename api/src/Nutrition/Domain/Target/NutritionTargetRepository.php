<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Nutrition\Domain\Exception\NutritionTargetNotFound;
use App\Shared\Domain\Identifier\UserId;

interface NutritionTargetRepository
{
    public function save(NutritionTarget $target): void;

    /**
     * @throws NutritionTargetNotFound also when it belongs to someone else
     */
    public function getOwnedBy(NutritionTargetId $id, UserId $userId): NutritionTarget;

    /**
     * The target in force on that day: the last one set among those effective on or before it.
     */
    public function inForceOn(UserId $userId, \DateTimeImmutable $day): ?NutritionTarget;

    /**
     * @return list<NutritionTarget> most recently set first
     */
    public function history(UserId $userId, int $limit): array;
}
