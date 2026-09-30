<?php

declare(strict_types=1);

namespace App\Tests\Api\Training;

use App\Tests\Support\Api\TrainingApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class NextWorkoutTest extends TrainingApiTestCase
{
    use ClockSensitiveTrait;

    private const string NOW = '2026-09-30 18:00:00';

    private string $programId;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(self::NOW);
        $this->client->disableReboot();
        $this->register();
    }

    public function testWithoutAnActiveProgramThereIsNoNextWorkout(): void
    {
        $this->call('GET', '/api/training/next-workout');

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains(['code' => 'training.no_active_program']);
    }

    public function testTheProgramIsFollowedInOrderGoingRound(): void
    {
        $this->program();

        self::assertSame(
            ['programName' => 'PPL', 'versionNumber' => 1, 'dayIndex' => 0, 'dayName' => 'Push', 'exerciseCount' => 2, 'lastDoneAt' => null, 'inProgress' => false],
            $this->next(),
        );

        $this->train('0199a0b1-0000-7000-8000-00000000e001', 0, '-3 hours');
        self::assertSame(['dayIndex' => 1, 'dayName' => 'Pull', 'lastDoneAt' => null], array_intersect_key($this->next(), ['dayIndex' => 1, 'dayName' => 1, 'lastDoneAt' => 1]));

        $this->train('0199a0b1-0000-7000-8000-00000000e002', 1, '-2 hours');
        $this->train('0199a0b1-0000-7000-8000-00000000e003', 2, '-1 hour');
        $next = $this->next();
        self::assertSame(0, $next['dayIndex'], 'after the last day, the first again');
        self::assertSame('2026-09-30T15:00:00+00:00', $next['lastDoneAt'], 'when the first day was started');
    }

    public function testAfterARevisionWithFewerDaysTheProgramStartsOverAndOldDaysDoNotCount(): void
    {
        $this->program();
        $this->train('0199a0b1-0000-7000-8000-00000000e031', 0, '-5 hours');
        $this->train('0199a0b1-0000-7000-8000-00000000e032', 1, '-4 hours');
        $this->train('0199a0b1-0000-7000-8000-00000000e033', 2, '-3 hours');
        // A workout of no program is not the program's.
        $this->call('PUT', '/api/workouts/0199a0b1-0000-7000-8000-00000000e034', ['startedAt' => self::time('-2 hours')]);
        $this->call('POST', '/api/workouts/0199a0b1-0000-7000-8000-00000000e034/finish', ['finishedAt' => self::time('-90 minutes')]);

        $bench = $this->exerciseId('developpe couche barre');
        $this->call('PUT', '/api/programs/'.$this->programId, ['name' => 'PPL', 'days' => [
            ['name' => 'Haut', 'blocks' => [['exercises' => [self::planned($bench)]]]],
            ['name' => 'Bas', 'blocks' => [['exercises' => [self::planned($bench)]]]],
        ]]);
        self::assertResponseIsSuccessful();

        $next = $this->next();
        self::assertSame(['versionNumber' => 2, 'dayIndex' => 0, 'dayName' => 'Haut', 'lastDoneAt' => null], array_intersect_key($next, ['versionNumber' => 1, 'dayIndex' => 1, 'dayName' => 1, 'lastDoneAt' => 1]), 'the last day done (the third) is beyond version 2: start over; its first day was never done');
    }

    public function testAWorkoutInProgressIsSaid(): void
    {
        $this->program();
        $this->call('PUT', '/api/workouts/0199a0b1-0000-7000-8000-00000000e010', ['programId' => $this->programId, 'versionNumber' => 1, 'dayIndex' => 0, 'startedAt' => self::time('-10 minutes')]);

        self::assertTrue($this->next()['inProgress']);
        self::assertSame(0, $this->next()['dayIndex'], 'an unfinished workout does not move the program on');
    }

    public function testTheNextWorkoutIsTheUsersOwn(): void
    {
        $this->program();
        $this->train('0199a0b1-0000-7000-8000-00000000e020', 0, '-1 hour');

        $this->useDevice('sam');
        $this->register('sam@example.com', 'Sam');
        $this->call('GET', '/api/training/next-workout');
        self::assertResponseStatusCodeSame(404, 'Alex\'s program is not Sam\'s');

        $this->useDevice('laptop');
        self::assertSame(1, $this->next()['dayIndex']);
    }

    private function program(): void
    {
        $bench = $this->exerciseId('developpe couche barre');
        $plank = $this->exerciseId('gainage planche');
        $day = static fn (string $name): array => ['name' => $name, 'blocks' => [['exercises' => [self::planned($bench)]], ['exercises' => [self::planned($plank, ['repMin' => 30, 'repMax' => 60])]]]];
        $this->programId = self::string($this->call('POST', '/api/programs', ['name' => 'PPL', 'days' => [$day('Push'), $day('Pull'), $day('Legs')]])->toArray(), 'id');
    }

    private function train(string $id, int $day, string $at): void
    {
        $this->call('PUT', '/api/workouts/'.$id, ['programId' => $this->programId, 'versionNumber' => 1, 'dayIndex' => $day, 'startedAt' => self::time($at)]);
        $this->call('POST', '/api/workouts/'.$id.'/finish', ['finishedAt' => new \DateTimeImmutable(self::time($at))->modify('+30 minutes')->format(\DateTimeInterface::ATOM)]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array<mixed>
     */
    private function next(): array
    {
        $next = $this->getJson('/api/training/next-workout');
        self::assertResponseIsSuccessful();
        unset($next['programId'], $next['@context'], $next['@id'], $next['@type']);

        return $next;
    }

    private static function time(string $when): string
    {
        return new \DateTimeImmutable(self::NOW.' UTC')->modify($when)->format(\DateTimeInterface::ATOM);
    }
}
