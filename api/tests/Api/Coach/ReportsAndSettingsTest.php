<?php

declare(strict_types=1);

namespace App\Tests\Api\Coach;

use App\Tests\Support\Api\CoachApiTestCase;

final class ReportsAndSettingsTest extends CoachApiTestCase
{
    public function testTheLastFinishedWeekGetsItsReportOnce(): void
    {
        $this->register();
        $this->targetSince(20);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => 0.5]);
        $this->eatDays(12, 1000);
        $this->stableWeight();

        $report = self::array($this->analyse(), 'report');

        self::assertSame('2026-09-21', $report['weekStart'], 'the Monday of the week before this one');
        self::assertSame([60.0, 60.0, 0.0, 0.5, 2500.0, 150.0, 7, 2500, 150, 0, null, 0], [
            self::number($report, 'startKg'), self::number($report, 'endKg'), self::number($report, 'changeKg'), self::number($report, 'paceKgPerWeek'),
            self::number($report, 'averageKcal'), self::number($report, 'averageProtein'), $report['loggedDays'], $report['targetKcal'], $report['targetProtein'],
            $report['sessions'], $report['plannedSessions'], $report['records'],
        ]);
        self::assertSame('below', $report['verdict'], 'stable when 0.5 kg a week is aimed at');
        self::assertSame('Passe à 2 650 kcal par jour (proposition à valider).', self::spaced(self::string($report, 'action')), 'what to do, not the title');
        self::assertSame(['calories', 'protein', 'weigh_ins'], array_column(self::array($report, 'adherence.parts'), 'part'));
        self::assertSame([7, 7, 3], array_column(self::array($report, 'adherence.parts'), 'done'), 'weighed on Mon, Wed, Fri, Sun: 4 of the 3 expected');
        self::assertSame(100, self::int($report, 'adherence.score'));

        $this->analyse(again: true);
        self::assertCount(1, $this->getJson('/api/coach/reports'), 'drawn once');
        self::assertSame('2026-09-21', self::string($this->getJson('/api/coach/reports'), '0.weekStart'));
    }

    public function testTheSettingsChangeTheToneOfWhatIsSaidNextOnly(): void
    {
        $this->register();
        self::assertSame(['personality' => 'direct', 'paceKgPerWeek' => null], $this->getJson('/api/coach/settings'), 'direct by default: actionable');
        $noTarget = self::recommendation($this->analyse(), 'calories.no_target');
        self::assertSame('Fixe ton objectif dans Nutrition.', $noTarget['body']);

        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => null]);
        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['personality' => 'neutral', 'paceKgPerWeek' => null]);
        $this->targetSince(1);
        $noPace = self::recommendation($this->analyse(again: true), 'calories.no_pace');

        self::assertSame('Le rythme visé n’est pas connu : règle-le dans les réglages du Coach.', self::spaced(self::string($noPace, 'body')));
        self::assertSame('Fixe ton objectif dans Nutrition.', self::string($this->getJson('/api/coach/recommendations/'.self::string($noTarget, 'id')), 'body'), 'what was said keeps its wording');
    }

    public function testTheSettingsAreChecked(): void
    {
        $this->register();

        $this->call('PUT', '/api/coach/settings', ['personality' => 'rude']);
        self::assertResponseStatusCodeSame(422);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'direct', 'paceKgPerWeek' => 2]);
        self::assertResponseStatusCodeSame(422);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'direct', 'paceKgPerWeek' => 0.123]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('coach.invalid_settings');
        self::assertSame('direct', self::string($this->getJson('/api/coach/settings'), 'personality'));
    }

    public function testReportsAndSettingsAreTheUsersOwn(): void
    {
        $this->register();
        $this->targetSince(20);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'encouraging', 'paceKgPerWeek' => 0.5]);
        $this->eatDays(12, 1000);
        $this->stableWeight();
        $this->analyse();
        self::assertCount(1, $this->getJson('/api/coach/reports'));

        $this->register('sam@example.com', 'Sam');

        self::assertSame([], $this->getJson('/api/coach/reports'));
        self::assertSame(['personality' => 'direct', 'paceKgPerWeek' => null], $this->getJson('/api/coach/settings'));
        $coach = $this->getJson('/api/coach?today='.self::TODAY);
        self::assertNull(self::at($coach, 'report'));
        self::assertNull(self::at($coach, 'adherence'), 'not analysed yet');
    }
}
