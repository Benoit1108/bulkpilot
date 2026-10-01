<?php

declare(strict_types=1);

namespace App\Media\Application\PersonalData;

use App\Media\Domain\MediaFileRepository;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;
use Psr\Clock\ClockInterface;

/**
 * Media keeps files, not data the user reads (the contexts that use them export what they mean):
 * nothing to export; the account's erasure deletes every file of its owner.
 */
final readonly class MediaPersonalData implements PersonalData
{
    public const string DOMAIN = 'media';

    public function __construct(private MediaFileRepository $files, private EventBus $eventBus, private ClockInterface $clock)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): ?string
    {
        return null;
    }

    public function periodFiltered(): bool
    {
        return false;
    }

    public function erasedLast(): bool
    {
        return false;
    }

    public function export(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        $now = $this->clock->now();
        foreach ($this->files->ofOwner($userId) as $file) {
            $file->erase($now);
            $this->files->remove($file);
            $this->eventBus->publish(...$file->pullDomainEvents());
        }
    }
}
