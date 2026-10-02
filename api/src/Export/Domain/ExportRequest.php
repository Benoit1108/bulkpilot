<?php

declare(strict_types=1);

namespace App\Export\Domain;

use App\Export\Domain\Exception\ExportNotReady;
use App\Export\Domain\Exception\InvalidExport;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * An export of the user's data (spec §104, ADR-0030): asked for, built by the worker into a private
 * archive, downloadable for a day, then deleted.
 */
class ExportRequest extends AggregateRoot
{
    /** How long the archive is kept once ready. */
    public const int KEPT_HOURS = 24;
    /** An export still open after this long belongs to a worker that died (out of memory, restart). */
    public const int STALE_MINUTES = 30;

    /**
     * @param list<string> $domains empty for all of them
     */
    private function __construct(
        private readonly ExportId $id,
        private readonly UserId $ownerId,
        private readonly ?\DateTimeImmutable $fromDay,
        private readonly ?\DateTimeImmutable $toDay,
        private readonly array $domains,
        private ExportStatus $status,
        private ?MediaId $archiveId,
        private readonly \DateTimeImmutable $requestedAt,
        private ?\DateTimeImmutable $readyAt,
        private ?\DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * @param list<string> $domains
     */
    public static function request(ExportId $id, UserId $owner, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, array $domains, \DateTimeImmutable $now): self
    {
        // Days, not hours: the same day at two hours is a one-day period.
        $from = $from?->setTime(0, 0);
        $to = $to?->setTime(0, 0);
        if (null !== $from && null !== $to && $from > $to) {
            throw InvalidExport::because('the period ends before it starts');
        }
        $domains = array_unique($domains);
        sort($domains);

        return new self($id, $owner, $from, $to, $domains, ExportStatus::Queued, null, $now, null, null);
    }

    /** The worker takes it; a redelivered message finds it taken and leaves it. */
    public function start(): bool
    {
        if (ExportStatus::Queued !== $this->status) {
            return false;
        }
        $this->status = ExportStatus::Processing;

        return true;
    }

    public function ready(MediaId $archiveId, \DateTimeImmutable $now): void
    {
        if (ExportStatus::Processing !== $this->status) {
            throw ExportNotReady::because('this export is not being made');
        }
        $this->status = ExportStatus::Ready;
        $this->archiveId = $archiveId;
        $this->readyAt = $now;
        $this->expiresAt = $now->modify(\sprintf('+%d hours', self::KEPT_HOURS));
    }

    /** It could not be made (the user asks again); a finished one stays as it is. */
    public function fail(): void
    {
        if ($this->status->isOpen()) {
            $this->status = ExportStatus::Failed;
        }
    }

    /** Past its time: no longer offered, its archive deleted after the commit. */
    public function expire(\DateTimeImmutable $now): void
    {
        if (ExportStatus::Ready !== $this->status || null === $this->archiveId) {
            return;
        }
        $this->status = ExportStatus::Expired;
        $this->recordThat(new ExportExpired($this->id, $this->ownerId, $this->archiveId, $now));
    }

    /** The archive to download, while it is kept. */
    public function archive(\DateTimeImmutable $now): MediaId
    {
        if (!$this->isDownloadable($now) || null === $this->archiveId) {
            throw ExportNotReady::because('this export cannot be downloaded');
        }

        return $this->archiveId;
    }

    public function isDownloadable(\DateTimeImmutable $now): bool
    {
        return ExportStatus::Ready === $this->status && null !== $this->expiresAt && $now < $this->expiresAt;
    }

    /** As the user sees it: a ready one past its time is expired. */
    public function status(\DateTimeImmutable $now): ExportStatus
    {
        return ExportStatus::Ready === $this->status && !$this->isDownloadable($now) ? ExportStatus::Expired : $this->status;
    }

    public function id(): ExportId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function fromDay(): ?\DateTimeImmutable
    {
        return $this->fromDay;
    }

    public function toDay(): ?\DateTimeImmutable
    {
        return $this->toDay;
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->domains;
    }

    public function requestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function readyAt(): ?\DateTimeImmutable
    {
        return $this->readyAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
