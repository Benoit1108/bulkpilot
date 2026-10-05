<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Health;

use App\System\Application\Health\Monitor;
use App\System\Application\Health\MonitorStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Free space where the API writes (cache, logs, local media, temporary dumps and exports): low
 * under 1 GiB or under 10 % of the volume, before a full disk breaks writes.
 */
final readonly class DiskMonitor implements Monitor
{
    public const int MIN_FREE_BYTES = 1024 ** 3;
    public const float MIN_FREE_SHARE = 0.10;

    /** @var \Closure(string): array{free: float|false, total: float|false} */
    private \Closure $measure;

    /**
     * @param (\Closure(string): array{free: float|false, total: float|false})|null $measure for the tests
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%/var')]
        private string $directory,
        ?\Closure $measure = null,
    ) {
        $this->measure = $measure ?? static fn (string $directory): array => ['free' => @disk_free_space($directory), 'total' => @disk_total_space($directory)];
    }

    public function statuses(): array
    {
        ['free' => $free, 'total' => $total] = ($this->measure)($this->directory);
        if (false === $free || false === $total || $total <= 0) {
            return ['disk' => MonitorStatus::Unknown];
        }

        return ['disk' => $free < self::MIN_FREE_BYTES || $free / $total < self::MIN_FREE_SHARE ? MonitorStatus::Low : MonitorStatus::Ok];
    }
}
