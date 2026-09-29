<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Coach\Application\Recommendation\RecommendationView;
use App\Coach\Application\Report\WeeklyReportView;
use App\Coach\Application\Settings\CoachSettingsView;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\RuleEngine;
use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

/**
 * What the last analysis left: no reading of the other contexts here (the adherence was kept with it).
 */
final readonly class GetCoachHandler implements QueryHandler
{
    public function __construct(
        private CoachSettingsRepository $settings,
        private RecommendationRepository $recommendations,
        private WeeklyReportRepository $reports,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetCoach $query): CoachView
    {
        $now = $this->clock->now();
        $shown = array_values(array_filter($this->recommendations->openOf($query->userId), static fn (Recommendation $recommendation): bool => $recommendation->isShown($now)));
        usort($shown, static fn (Recommendation $a, Recommendation $b): int => Kind::compare($a->level(), $a->kind(), $b->level(), $b->kind()));
        $report = $this->reports->latestOf($query->userId, 1)[0] ?? null;

        return new CoachView(
            CoachSettingsView::of($this->settings->ofOrDefault($query->userId, $now)),
            array_map(static fn (Recommendation $recommendation): RecommendationView => RecommendationView::of($recommendation, $now), \array_slice($shown, 0, RuleEngine::MAX_RECOMMENDATIONS)),
            null === $report ? null : WeeklyReportView::of($report),
        );
    }
}
