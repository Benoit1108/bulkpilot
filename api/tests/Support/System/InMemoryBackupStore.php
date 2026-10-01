<?php

declare(strict_types=1);

namespace App\Tests\Support\System;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\BackupStore;

final class InMemoryBackupStore implements BackupStore
{
    /** @var array<string, string> contents by key */
    public array $objects = [];
    public bool $failing = false;
    /** Only uploads fail (the bucket refuses writes): listing and deleting still work. */
    public bool $failingPuts = false;

    public function put(string $key, string $path): void
    {
        $this->failIfAsked();
        if ($this->failingPuts) {
            throw BackupFailed::because('the bucket refused the upload');
        }
        $this->objects[$key] = (string) file_get_contents($path);
    }

    public function keys(string $prefix): array
    {
        $this->failIfAsked();

        return array_values(array_filter(array_keys($this->objects), static fn (string $key): bool => str_starts_with($key, $prefix)));
    }

    public function fetch(string $key, string $path): void
    {
        $this->failIfAsked();
        file_put_contents($path, $this->objects[$key] ?? throw BackupFailed::because('no such backup'));
    }

    public function delete(string $key): void
    {
        $this->failIfAsked();
        unset($this->objects[$key]);
    }

    private function failIfAsked(): void
    {
        if ($this->failing) {
            throw BackupFailed::because('the bucket is unreachable');
        }
    }
}
