<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

use App\Shared\Application\File\TemporaryFile;
use App\Shared\Application\PersonalData\ErasedAccounts;
use App\Shared\Domain\Identifier\UserId;
use App\System\Domain\Backup\ErasureMarker;

/**
 * The erased accounts, kept as empty objects next to the backups they concern (ADR-0031): a
 * database restored from a backup is then erased of them again.
 */
final readonly class BackupErasureLog implements ErasedAccounts
{
    public function __construct(private BackupStore $store, private bool $enabled)
    {
    }

    public function remember(UserId $userId, \DateTimeImmutable $erasedAt): void
    {
        if (!$this->enabled) {
            return;
        }
        $empty = TemporaryFile::create('bulkpilot-erasure-');
        try {
            $this->store->put(ErasureMarker::of($userId->value, $erasedAt)->value, $empty);
        } finally {
            TemporaryFile::remove($empty);
        }
    }

    public function all(): array
    {
        if (!$this->enabled) {
            return [];
        }
        $ids = [];
        foreach ($this->store->keys(ErasureMarker::PREFIX) as $key) {
            $marker = ErasureMarker::parse($key);
            if (null !== $marker) {
                $ids[$marker->userId] = new UserId($marker->userId);
            }
        }

        return array_values($ids);
    }
}
