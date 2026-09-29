<?php

declare(strict_types=1);

namespace App\Tests\Api\Coach;

use App\Tests\Support\Api\CoachApiTestCase;

final class RecommendationsTest extends CoachApiTestCase
{
    public function testTheCoachIsForSignedInUsers(): void
    {
        $this->call('GET', '/api/coach');
        self::assertResponseStatusCodeSame(401);
        $this->call('POST', '/api/coach/analysis', ['again' => true]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testANewUserIsToldWhatTheCoachNeedsAndOnlyTheWeighInsCount(): void
    {
        $this->register();

        $coach = $this->analyse();

        self::assertSame(self::TODAY, self::string($coach, 'today'));
        self::assertSame(self::TODAY, self::string($coach, 'analysedOn'));
        self::assertSame(['calories.no_target', 'weigh_ins.few'], self::kinds($coach), 'the most important first');
        foreach (self::array($coach, 'recommendations') as $recommendation) {
            self::assertSame(['info', 'pending', null, 'fake'], [self::at($recommendation, 'level'), self::at($recommendation, 'status'), self::at($recommendation, 'proposal'), self::at($recommendation, 'provider')]);
            self::assertNotSame([], self::array($recommendation, 'why'));
        }
        self::assertSame(['score' => null, 'parts' => [['part' => 'weigh_ins', 'done' => 0, 'expected' => 3, 'score' => 0]]], self::array($coach, 'adherence'), 'no target nor program: nothing else is expected; nothing recorded: no score');
        self::assertNull(self::at($coach, 'report'), 'nothing happened last week');
        self::assertSame([], $this->getJson('/api/coach/reports'));
    }

    public function testAStableWeightOnATargetReachedProposesMoreCaloriesThatAcceptingSetsFromTomorrow(): void
    {
        $this->register();
        $this->targetSince(20);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => 0.5]);
        self::assertResponseStatusCodeSame(200);
        $this->eatDays(12, 1000);
        $this->stableWeight();

        $coach = $this->analyse();

        self::assertSame('calories.increase', self::string($coach, 'recommendations.0.kind'), 'a proposal comes first');
        $increase = self::recommendation($coach, 'calories.increase');
        self::assertSame(['priority', ['fromKcal' => 2500, 'byKcal' => 150, 'toKcal' => 2650, 'carbohydrateChangeGrams' => 38]], [$increase['level'], $increase['proposal']]);
        self::assertSame('Proposition : +150 kcal', self::spaced(self::string($increase, 'title')));
        self::assertCount(4, self::array($increase, 'why'), 'the trend, what was eaten, the target, the share of it (spec §69)');
        self::assertStringContainsString('100', self::string($increase, 'why.3'));
        self::assertSame([2500, 2500.0, 12, 100, 0.5], [self::int($increase, 'figures.targetKcal'), self::number($increase, 'figures.averageKcal'), self::int($increase, 'figures.loggedDays'), self::int($increase, 'figures.percentOfTarget'), self::number($increase, 'figures.paceKgPerWeek')]);
        self::assertSame(0.0, self::number($increase, 'figures.kgPerWeek'));

        $accepted = $this->decide(self::string($increase, 'id'), 'accept');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('accepted', $accepted['status']);
        self::assertNotNull($accepted['decidedAt']);

        $targets = $this->getJson('/api/nutrition/targets');
        self::assertCount(2, $targets);
        $target = self::array($targets, '0');
        self::assertSame('manual', self::string($targets, '1.source'), 'the most recently set first');
        self::assertStringStartsWith('2026-10-01', self::string($target, 'effectiveFrom'), 'from tomorrow');
        self::assertSame(['energyKcal' => 2650, 'proteinGrams' => 150, 'carbohydrateGrams' => 344, 'fatGrams' => 75, 'source' => 'coach'], array_intersect_key($target, array_flip(['energyKcal', 'proteinGrams', 'carbohydrateGrams', 'fatGrams', 'source'])), 'protein and fat kept, the carbohydrates make up 2 650 kcal');

        $this->decide(self::string($increase, 'id'), 'reject');
        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('coach.recommendation_closed');
        self::assertNotContains('calories.increase', self::kinds($this->analyse(again: true)), 'just decided: not offered again');
    }

    public function testEatingWellBelowTheTargetAsksToReachItFirst(): void
    {
        $this->register();
        $this->targetSince(20);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => 0.5]);
        $this->eatDays(12, 500);
        $this->stableWeight();

        $reach = self::recommendation($this->analyse(), 'calories.reach_target_first');

        self::assertSame(['priority', null, 50], [$reach['level'], $reach['proposal'], self::int($reach, 'figures.percentOfTarget')]);
        self::assertStringContainsString('2 500', self::spaced(self::string($reach, 'title')));
    }

    public function testAProposalMadeOnATargetChangedSinceIsRefused(): void
    {
        $this->register();
        $this->targetSince(20);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => 0.5]);
        $this->eatDays(12, 1000);
        $this->stableWeight();
        $increase = self::recommendation($this->analyse(), 'calories.increase');
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => self::TODAY, 'energyKcal' => 2400, 'proteinGrams' => 150, 'carbohydrateGrams' => 275, 'fatGrams' => 75]);
        self::assertResponseStatusCodeSame(201);

        $this->decide(self::string($increase, 'id'), 'accept');

        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('coach.target_changed');
        self::assertSame([2400, 'manual'], [self::int($this->getJson('/api/nutrition/targets'), '0.energyKcal'), self::string($this->getJson('/api/nutrition/targets'), '0.source')], 'the target is untouched');
        self::assertSame('pending', self::string($this->getJson('/api/coach/recommendations/'.self::string($increase, 'id')), 'status'), 'the refused decision is not kept');
    }

    public function testPostponedIsSetAsideAndRejectedIsNotOfferedAgain(): void
    {
        $this->register();
        $coach = $this->analyse();
        $noTarget = self::string(self::recommendation($coach, 'calories.no_target'), 'id');
        $fewWeighIns = self::string(self::recommendation($coach, 'weigh_ins.few'), 'id');

        self::assertSame('postponed', $this->decide($noTarget, 'postpone')['status']);
        self::assertNotNull($this->decide($noTarget, 'postpone')['postponedUntil'], 'a postponed one can be put aside again');
        self::assertSame('rejected', $this->decide($fewWeighIns, 'reject')['status']);

        self::assertSame([], self::kinds($this->getJson('/api/coach?today='.self::TODAY)), 'postponed is hidden, rejected is closed');
        self::assertSame([], self::kinds($this->analyse(again: true)), 'still true, still hidden: nothing new');
        $history = $this->getJson('/api/coach/recommendations');
        self::assertSame([[$fewWeighIns, 'rejected'], [$noTarget, 'postponed']], array_map(static fn (mixed $one): array => [self::at($one, 'id'), self::at($one, 'status')], $history), 'the latest first, then by id');

        $this->decide($noTarget, 'maybe');
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheAnalysisRunsOnceADayUnlessAskedAgain(): void
    {
        $this->register();
        $first = $this->analyse();
        $this->targetSince(1);

        self::assertSame(self::array($first, 'recommendations'), self::array($this->analyse(), 'recommendations'), 'already analysed today');
        $again = $this->analyse(again: true);

        self::assertSame(['calories.no_pace', 'journal.incomplete', 'weigh_ins.few'], self::kinds($again), 'the target is there now, the pace is not');
        self::assertSame(self::string(self::recommendation($first, 'weigh_ins.few'), 'id'), self::string(self::recommendation($again, 'weigh_ins.few'), 'id'), 'still supported: the same recommendation');
        $noTarget = self::string(self::recommendation($first, 'calories.no_target'), 'id');
        self::assertSame('expired', self::string($this->getJson('/api/coach/recommendations/'.$noTarget), 'status'), 'no longer supported by the data');
        $history = $this->getJson('/api/coach/recommendations');
        self::assertCount(4, $history);

        // Page by page, one at a time: those of one analysis share their time, none is skipped.
        $paged = [];
        $query = '?limit=1';
        for ($page = 0; $page < 5; ++$page) {
            $one = $this->getJson('/api/coach/recommendations'.$query);
            if ([] === $one) {
                break;
            }
            $last = self::array($one, '0');
            $paged[] = self::string($last, 'id');
            $query = '?limit=1&before='.rawurlencode(self::string($last, 'createdAt')).'&beforeId='.self::string($last, 'id');
        }
        self::assertSame(array_map(static fn (mixed $one): string => self::string($one, 'id'), $history), $paged);

        $this->call('GET', '/api/coach/recommendations?before=soon');
        self::assertResponseStatusCodeSame(400);
        $this->call('GET', '/api/coach/recommendations?before=2026-09-30T10:00:00%2B00:00&beforeId=nope');
        self::assertResponseStatusCodeSame(422, 'refused by the declared parameter itself (a uuid)');
    }

    public function testRecommendationsAreTheUsersOwn(): void
    {
        $this->register();
        $id = self::string($this->analyse(), 'recommendations.0.id');

        $this->register('sam@example.com', 'Sam');
        $this->call('GET', '/api/coach/recommendations/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('coach.recommendation_not_found');
        $this->decide($id, 'accept');
        self::assertResponseStatusCodeSame(404);
        $this->call('GET', '/api/coach/recommendations/not-an-id');
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->getJson('/api/coach/recommendations'));
        self::assertSame([], self::kinds($this->getJson('/api/coach?today='.self::TODAY)));
    }

    public function testAnAcceptedChangeNutritionWouldRefuseIsUnreachable(): void
    {
        // 1 050 kcal, protein and fat alone giving 1 030: a cut too slow asks for 1 000 kcal.
        $this->register();
        $this->targetSince(20, ['energyKcal' => 1050, 'proteinGrams' => 100, 'carbohydrateGrams' => 5, 'fatGrams' => 70]);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'direct', 'paceKgPerWeek' => -0.5]);
        $this->eatDays(12, 420);
        $this->stableWeight();
        $decrease = self::recommendation($this->analyse(), 'calories.decrease');
        self::assertSame(-50, self::int($decrease, 'proposal.byKcal'), 'never below 1 000 kcal');

        $this->decide(self::string($decrease, 'id'), 'accept');

        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('coach.target_unreachable');
        self::assertCount(1, $this->getJson('/api/nutrition/targets'), 'the target is untouched');
        self::assertSame('pending', self::string($this->getJson('/api/coach/recommendations/'.self::string($decrease, 'id')), 'status'));
    }

    public function testTheAnalysisNeverGoesBackToAnEarlierDay(): void
    {
        $this->register();
        $this->analyse(body: ['today' => '2026-10-01']);
        $this->targetSince(1);

        $behind = $this->analyse(body: ['today' => self::TODAY]);

        self::assertSame('2026-10-01', self::string($behind, 'analysedOn'), 'a device a day behind reads, it does not analyse again');
        self::assertContains('calories.no_target', self::kinds($behind));
    }

    public function testTodayIsTheDevicesWithinADayOfTheServers(): void
    {
        $this->register();

        self::assertSame('2026-10-01', self::string($this->getJson('/api/coach?today=2026-10-01'), 'today'));
        self::assertSame('2026-09-29', self::string($this->getJson('/api/coach?today=2026-09-29'), 'today'));

        $this->call('GET', '/api/coach?today=2026-10-05');
        self::assertResponseStatusCodeSame(400);
        $this->call('POST', '/api/coach/analysis', ['today' => '2026-09-20']);
        self::assertResponseStatusCodeSame(400);
    }
}
