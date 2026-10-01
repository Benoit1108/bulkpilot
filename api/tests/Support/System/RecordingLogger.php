<?php

declare(strict_types=1);

namespace App\Tests\Support\System;

use Psr\Log\AbstractLogger;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<string> "level message"
     */
    public function lines(): array
    {
        return array_map(static fn (array $record): string => $record['level'].' '.$record['message'], $this->records);
    }
}
