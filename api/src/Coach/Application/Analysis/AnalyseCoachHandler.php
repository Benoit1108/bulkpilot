<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Coach\Application\Port\AiProvider;
use App\Coach\Application\Port\CoachAnalysis;
use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Coach\Domain\Report\WeeklyReport;
use App\Coach\Domain\Report\WeeklyReportId;
use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Level;
use App\Coach\Domain\Rule\RuleEngine;
use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Calendar\Days;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use Psr\Clock\ClockInterface;

/**
 * The analysis (spec §62–70): the rules find, the most important three are offered, each phrased
 * by the AI provider. A recommendation still supported by the data is kept as it is (with the
 * figures it was made on); one that no longer is expires. A kind the user just decided on is not
 * offered again for a while, and the last finished week gets its report.
 *
 * The settings are versioned: of two analyses at the same time, the second one fails (409) rather
 * than offering everything twice. The provider is called inside the transaction; a real (slow)
 * one will phrase after the commit instead (ADR-0028).
 */
final readonly class AnalyseCoachHandler implements CommandHandler
{
    /** After the user decided on a kind of recommendation, it is not offered again for this long. */
    public const int QUIET_DAYS = 7;

    public function __construct(
        private CoachSettingsRepository $settings,
        private RecommendationRepository $recommendations,
        private WeeklyReportRepository $reports,
        private FactsReader $facts,
        private AiProvider $ai,
        private EventBus $eventBus,
        private UuidGenerator $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AnalyseCoach $command): void
    {
        $now = $this->clock->now();
        $today = $command->today->setTime(0, 0);
        $settings = $this->settings->ofOrDefault($command->userId, $now);
        if (!$command->again && $settings->hasAnalysed($today)) {
            return;
        }

        $facts = $this->facts->on($command->userId, $today, $settings->paceKgPerWeek());
        $offered = $this->offer($command->userId, $settings, $facts, $now);
        $this->report($command->userId, $settings, $today, $offered, $now);
        $settings->markAnalysed($today, Adherence::of($facts->target, $facts->week, $facts->weight->weighIns7, $facts->training));
        $this->settings->save($settings);
    }

    /**
     * @return list<Recommendation> the recommendations offered after the analysis, the most important first
     */
    private function offer(UserId $userId, CoachSettings $settings, Facts $facts, \DateTimeImmutable $now): array
    {
        $quiet = array_map(
            static fn (Recommendation $decided): string => $decided->kind()->value,
            $this->recommendations->decidedSince($userId, $now->modify(\sprintf('-%d days', self::QUIET_DAYS))),
        );
        $findings = \array_slice(array_values(array_filter(
            RuleEngine::findings($facts),
            static fn (Finding $finding): bool => !\in_array($finding->kind->value, $quiet, true),
        )), 0, RuleEngine::MAX_RECOMMENDATIONS);

        $open = $this->recommendations->openOf($userId);
        $offered = [];
        foreach ($findings as $finding) {
            $kept = null;
            foreach ($open as $recommendation) {
                if ($recommendation->isOpen($now) && $recommendation->matches($finding) && !\in_array($recommendation, $offered, true)) {
                    $kept = $recommendation;
                    break;
                }
            }
            $offered[] = $kept ?? $this->make($userId, $finding, $settings, $now);
        }
        foreach ($open as $recommendation) {
            if (!\in_array($recommendation, $offered, true)) {
                $recommendation->expire($now);
                $this->recommendations->save($recommendation);
            }
        }

        return $offered;
    }

    private function make(UserId $userId, Finding $finding, CoachSettings $settings, \DateTimeImmutable $now): Recommendation
    {
        [$sent, $names] = Placeholders::hide($finding);
        $message = Placeholders::reveal($this->ai->generateRecommendation(new CoachAnalysis($sent, $settings->personality())), $names);
        $recommendation = Recommendation::make(new RecommendationId($this->uuids->generate()), $userId, $finding, $message, $now);
        $this->recommendations->save($recommendation);
        $this->eventBus->publish(...$recommendation->pullDomainEvents());

        return $recommendation;
    }

    /**
     * @param list<Recommendation> $offered
     */
    private function report(UserId $userId, CoachSettings $settings, \DateTimeImmutable $today, array $offered, \DateTimeImmutable $now): void
    {
        $monday = Days::weekOf($today)->modify('-7 days');
        if ($this->reports->has($userId, $monday)) {
            return;
        }
        $week = $this->facts->week($userId, $monday, $settings->paceKgPerWeek());
        if ($week->figures->isEmpty()) {
            return;
        }
        $action = null;
        foreach ($offered as $recommendation) {
            if (Level::Info !== $recommendation->level()) {
                $action = $recommendation->message()->action;
                break;
            }
        }

        $this->reports->save(WeeklyReport::draw(new WeeklyReportId($this->uuids->generate()), $userId, $monday, $week, $action, $now));
    }
}
