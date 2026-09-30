<?php

declare(strict_types=1);

namespace App\Identity\Application\Preferences;

use App\Identity\Domain\Dashboard\DashboardLayout;
use App\Identity\Domain\Dashboard\WidgetPlacement;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetDashboardLayoutHandler implements QueryHandler
{
    public function __construct(private PreferencesRepository $preferences)
    {
    }

    /**
     * @return list<WidgetView> every widget, in the user's order (the default one until arranged)
     */
    public function __invoke(GetDashboardLayout $query): array
    {
        return array_map(
            static fn (WidgetPlacement $placement): WidgetView => new WidgetView($placement->widget->value, $placement->visible),
            ($this->preferences->of($query->userId)?->dashboardLayout() ?? DashboardLayout::default())->placements,
        );
    }
}
