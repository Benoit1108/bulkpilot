<?php

declare(strict_types=1);

namespace App\Progression\Domain\Photo;

use App\Progression\Domain\Exception\ProgressPhotoNotFound;
use App\Shared\Domain\Identifier\UserId;

interface ProgressPhotoRepository
{
    public function save(ProgressPhoto $photo): void;

    public function remove(ProgressPhoto $photo): void;

    /**
     * @throws ProgressPhotoNotFound also when it belongs to someone else
     */
    public function getOwnedBy(ProgressPhotoId $id, UserId $userId): ProgressPhoto;

    /**
     * @return list<ProgressPhoto> the most recent day first, in the order they were added within a day
     */
    public function ofUser(UserId $userId): array;

    public function countOf(UserId $userId): int;
}
