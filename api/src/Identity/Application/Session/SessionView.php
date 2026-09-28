<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

final readonly class SessionView
{
    public function __construct(
        public string $id,
        public string $deviceLabel,
        public \DateTimeImmutable $openedAt,
        public \DateTimeImmutable $lastSeenAt,
        public bool $current,
    ) {
    }
}
