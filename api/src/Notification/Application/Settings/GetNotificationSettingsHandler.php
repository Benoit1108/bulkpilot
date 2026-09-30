<?php

declare(strict_types=1);

namespace App\Notification\Application\Settings;

use App\Notification\Domain\Settings\NotificationSettingsRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class GetNotificationSettingsHandler implements QueryHandler
{
    public function __construct(private NotificationSettingsRepository $settings, private ClockInterface $clock)
    {
    }

    public function __invoke(GetNotificationSettings $query): NotificationSettingsView
    {
        return NotificationSettingsView::of($this->settings->ofOrDefault($query->userId, $this->clock->now()));
    }
}
