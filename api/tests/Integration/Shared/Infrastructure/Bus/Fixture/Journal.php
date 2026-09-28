<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

/**
 * Records what the probe handlers did, in order.
 */
final class Journal
{
    /** @var list<string> */
    public array $entries = [];

    public function write(string $entry): void
    {
        $this->entries[] = $entry;
    }
}
