<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Coach\Application\Recommendation\RecommendationView;
use App\Coach\Application\Report\WeeklyReportView;
use App\Coach\Application\Settings\CoachSettingsView;

final readonly class CoachView
{
    /**
     * @param list<RecommendationView> $recommendations shown now (postponed ones aside), the most important first
     */
    public function __construct(
        public CoachSettingsView $settings,
        public array $recommendations,
        public ?WeeklyReportView $report,
    ) {
    }
}
