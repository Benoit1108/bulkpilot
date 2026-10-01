<?php

declare(strict_types=1);

namespace App\Shared\Application\PersonalData;

use App\Shared\Domain\Identifier\UserId;

/**
 * What a context holds of a user (spec §103–104, ADR-0030), implemented by every context that
 * keeps personal data and collected by tag: the export reads it, the account deletion erases it.
 * A context added later only adds its implementation: the export and the erasure find it.
 */
interface PersonalData
{
    /** The context's name in the export (`nutrition`, `training`…), also the domain a scope picks. */
    public function domain(): string;

    /** What the user picks and reads in the export (« Nutrition »); null when there is nothing to export. */
    public function title(): ?string;

    /** Whether an export's period narrows what is exported (the account itself is always complete). */
    public function periodFiltered(): bool;

    /**
     * Whether it is erased after every other context: the one holding the account, the key the
     * others are erased by.
     */
    public function erasedLast(): bool;

    /**
     * The user's data as tables, filtered by the scope's period where it applies.
     *
     * @return list<Dataset>
     */
    public function export(UserId $userId, ExportScope $scope): array;

    /**
     * The files that go with the data (progress photos), read by the export.
     *
     * @return list<ExportFile>
     */
    public function files(UserId $userId, ExportScope $scope): array;

    /**
     * Removes everything the context holds of the user for good, files included (deleted once
     * the erasure committed, and retried until they are). Erasing again changes nothing.
     */
    public function erase(UserId $userId): void;
}
