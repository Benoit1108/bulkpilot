<?php

declare(strict_types=1);

namespace App\Export\Domain;

use App\Export\Domain\Exception\ExportNotFound;
use App\Shared\Domain\Identifier\UserId;

interface ExportRequestRepository
{
    public function save(ExportRequest $export): void;

    public function remove(ExportRequest $export): void;

    /** Also someone else's: the worker loads it by id. */
    public function find(ExportId $id): ?ExportRequest;

    /**
     * @throws ExportNotFound also when it is someone else's
     */
    public function getOwnedBy(ExportId $id, UserId $userId): ExportRequest;

    public function hasOpen(UserId $userId): bool;

    /**
     * @return list<ExportRequest> the latest first
     */
    public function latestOf(UserId $userId, int $limit): array;

    /**
     * @return list<ExportRequest>
     */
    public function ofOwner(UserId $userId): array;

    /**
     * @return list<ExportRequest> ready ones whose archive is past its time
     */
    public function expiredAt(\DateTimeImmutable $now): array;

    /**
     * @return list<ExportRequest> queued or being made, asked for before that time
     */
    public function openSince(\DateTimeImmutable $before): array;
}
