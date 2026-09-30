<?php

declare(strict_types=1);

namespace App\Notification\Application\Settings;

use App\Notification\Domain\Exception\InvalidNotificationSettings;
use App\Notification\Domain\Settings\NotificationSettingsRepository;
use App\Notification\Domain\Settings\QuietHours;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ChangeNotificationSettingsHandler implements CommandHandler
{
    public function __construct(private NotificationSettingsRepository $settings, private ClockInterface $clock)
    {
    }

    public function __invoke(ChangeNotificationSettings $command): void
    {
        $now = $this->clock->now();
        $settings = $this->settings->ofOrDefault($command->userId, $now);
        $quiet = match (true) {
            null === $command->quietFrom && null === $command->quietTo => null,
            null === $command->quietFrom || null === $command->quietTo => throw InvalidNotificationSettings::because('quiet hours have a start and an end, or none'),
            default => new QuietHours($command->quietFrom, $command->quietTo),
        };
        $settings->change($command->enabled, $quiet, $now);
        $this->settings->save($settings);
    }
}
