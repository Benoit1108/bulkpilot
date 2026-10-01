<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class RequestExport implements Command
{
    /**
     * @param list<string> $domains empty for all of them
     */
    public function __construct(
        public ExportId $exportId,
        public UserId $userId,
        public ?\DateTimeImmutable $from,
        public ?\DateTimeImmutable $to,
        public array $domains,
    ) {
    }
}
