<?php

declare(strict_types=1);

namespace App\Identity\Application\Preferences;

use App\Identity\Domain\Dashboard\DashboardLayout;
use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ArrangeDashboardHandler implements CommandHandler
{
    public function __construct(private PreferencesRepository $preferences, private ClockInterface $clock)
    {
    }

    public function __invoke(ArrangeDashboard $command): void
    {
        $layout = DashboardLayout::arranged($command->widgets);
        $now = $this->clock->now();
        $preferences = $this->preferences->of($command->userId) ?? Preferences::defaults($command->userId, $now);
        $preferences->arrangeDashboard($layout, $now);
        $this->preferences->save($preferences);
    }
}
