<?php

declare(strict_types=1);

namespace App\Coach\Application\Settings;

use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class GetCoachSettingsHandler implements QueryHandler
{
    public function __construct(private CoachSettingsRepository $settings, private ClockInterface $clock)
    {
    }

    public function __invoke(GetCoachSettings $query): CoachSettingsView
    {
        return CoachSettingsView::of($this->settings->ofOrDefault($query->userId, $this->clock->now()));
    }
}
