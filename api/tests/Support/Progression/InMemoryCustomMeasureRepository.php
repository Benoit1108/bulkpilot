<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Progression\Domain\Exception\MeasureNotFound;
use App\Progression\Domain\Measurement\CustomMeasure;
use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryCustomMeasureRepository implements CustomMeasureRepository
{
    /** @var array<string, CustomMeasure> */
    public array $measures = [];

    public function save(CustomMeasure $measure): void
    {
        $this->measures[$measure->key()] = $measure;
    }

    public function getOwnedBy(CustomMeasureId $id, UserId $userId): CustomMeasure
    {
        $measure = $this->measures[$id->value] ?? null;
        if (null === $measure || !$measure->ownerId()->equals($userId)) {
            throw MeasureNotFound::withKey($id->value);
        }

        return $measure;
    }

    public function ofUser(UserId $userId, bool $withArchived = false): array
    {
        return array_values(array_filter($this->measures, static fn (CustomMeasure $measure): bool => $measure->ownerId()->equals($userId) && ($withArchived || !$measure->isArchived())));
    }
}
