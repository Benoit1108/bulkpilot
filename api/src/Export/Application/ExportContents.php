<?php

declare(strict_types=1);

namespace App\Export\Application;

/**
 * What the archive says of itself: when it was made, for which period, the domains' titles, and
 * the time zone its moments are written in.
 */
final readonly class ExportContents
{
    /**
     * @param array<string, string> $titles by domain
     */
    public function __construct(
        public \DateTimeImmutable $madeAt,
        public ?\DateTimeImmutable $from,
        public ?\DateTimeImmutable $to,
        public array $titles,
        /** The user's: every moment in the archive is written in it. */
        public string $timeZone = 'UTC',
    ) {
    }
}
