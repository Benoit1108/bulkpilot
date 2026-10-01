<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportStatus;

final readonly class ExportView
{
    /**
     * @param list<string> $domains
     */
    public function __construct(
        public string $id,
        public ExportStatus $status,
        public ?\DateTimeImmutable $from,
        public ?\DateTimeImmutable $to,
        public array $domains,
        public \DateTimeImmutable $requestedAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {
    }

    public static function of(ExportRequest $export, \DateTimeImmutable $now): self
    {
        return new self(
            $export->id()->value,
            $export->status($now),
            $export->fromDay(),
            $export->toDay(),
            $export->domains(),
            $export->requestedAt(),
            $export->readyAt(),
            $export->expiresAt(),
        );
    }
}
