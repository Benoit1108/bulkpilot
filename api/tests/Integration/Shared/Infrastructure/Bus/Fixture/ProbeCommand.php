<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Command\Command;

final readonly class ProbeCommand implements Command
{
    public function __construct(
        public string $id,
        public bool $failInHandler = false,
        public bool $failInSubscriber = false,
    ) {
    }
}
