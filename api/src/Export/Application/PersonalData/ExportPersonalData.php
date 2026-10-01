<?php

declare(strict_types=1);

namespace App\Export\Application\PersonalData;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;

/**
 * The user's exports: nothing to export of them; erased with the account (their archives are
 * Media's files, erased with the others).
 */
final readonly class ExportPersonalData implements PersonalData
{
    public const string DOMAIN = 'export';

    public function __construct(private ExportRequestRepository $exports)
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
        foreach ($this->exports->ofOwner($userId) as $export) {
            $this->exports->remove($export);
        }
    }
}
