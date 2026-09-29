<?php

declare(strict_types=1);

namespace App\Tests\Api\Training;

use App\Tests\Support\Api\TrainingApiTestCase;

final class WorkoutsTest extends TrainingApiTestCase
{
    private const string WORKOUT = '0199a0b1-0000-7000-8000-00000000d001';
    private const string NEXT = '0199a0b1-0000-7000-8000-00000000d002';

    private string $programId;
    private string $bench;
    private string $plank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->register();
        $this->bench = $this->exerciseId('developpe couche barre');
        $this->plank = $this->exerciseId('gainage planche');
        $this->programId = self::string($this->call('POST', '/api/programs', ['name' => 'Full', 'days' => [
            ['name' => 'Full A', 'blocks' => [['exercises' => [self::planned($this->bench, ['repMin' => 8, 'repMax' => 12, 'strategy' => 'double_progression'])]], ['exercises' => [self::planned($this->plank, ['repMin' => 30, 'repMax' => 60])]]]],
        ]])->toArray(), 'id');
    }

    public function testAWorkoutIsStartedSetBySetThenFinished(): void
    {
        $preview = $this->getJson('/api/workouts/preview?programId='.$this->programId.'&day=0');
        self::assertSame(['Full A', 'Full'], [self::string($preview, 'programDay.dayName'), self::string($preview, 'programDay.programName')]);
        self::assertSame([0, 1], array_column(self::array($preview, 'exercises'), 'index'));
        self::assertNull(self::at($preview, 'exercises.0.previous'));

        $started = $this->start(self::WORKOUT);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['id' => self::WORKOUT, 'status' => 'in_progress'], array_intersect_key($started, ['id' => 1, 'status' => 1]));
        self::assertSame('Développé couché à la barre', self::string($started, 'exercises.0.name'));
        $this->start(self::WORKOUT);
        self::assertResponseStatusCodeSame(200, 'starting again changes nothing');

        foreach ([['0199a0b1-0000-7000-8000-000000000501', 80, 12], ['0199a0b1-0000-7000-8000-000000000502', 80, 12], ['0199a0b1-0000-7000-8000-000000000503', 80, 12]] as [$set, $load, $reps]) {
            $this->call('PUT', '/api/workouts/'.self::WORKOUT.'/sets/'.$set, ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => $load, 'reps' => $reps, 'rir' => 1, 'doneAt' => $this->time('-20 minutes')]);
            self::assertResponseStatusCodeSame(204);
        }
        $this->call('PUT', '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000503', ['exerciseIndex' => 0, 'loadKg' => 80, 'reps' => 12, 'rir' => 0, 'doneAt' => $this->time('-20 minutes')]);
        $this->call('PUT', '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000504', ['exerciseIndex' => 1, 'seconds' => 45, 'doneAt' => $this->time('-10 minutes')]);
        $this->call('DELETE', '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000504');
        self::assertResponseStatusCodeSame(204);
        $sets = self::array($this->getJson('/api/workouts/'.self::WORKOUT), 'sets');
        self::assertCount(3, $sets, 'sent twice, recorded once; removed');
        self::assertSame(0, self::at($sets, '2.rir'), 'the correction is kept');
        self::assertSame([self::WORKOUT], array_column($this->getJson('/api/workouts/in-progress'), 'id'));

        $finished = $this->call('POST', '/api/workouts/'.self::WORKOUT.'/finish', ['finishedAt' => $this->time('now')])->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('finished', self::string($finished, 'status'));
        self::assertSame([], $this->getJson('/api/workouts/in-progress'));
        self::assertSame(['exercises' => 1, 'workingSets' => 3, 'volumeKg' => 2880.0], array_intersect_key(self::array($this->getJson('/api/workouts'), '0'), ['exercises' => 1, 'workingSets' => 1, 'volumeKg' => 1]));

        $next = $this->getJson('/api/workouts/preview?programId='.$this->programId.'&day=0');
        self::assertSame([80.0, 80.0, 80.0], array_column(self::array($next, 'exercises.0.previous.sets'), 'loadKg'));
        self::assertSame(['reason' => 'increase_load', 'loadKg' => 82.5, 'reps' => 8, 'seconds' => null], self::array($next, 'exercises.0.suggestion'));
        self::assertContains('load', array_column(self::array($next, 'exercises.0.records'), 'kind'));

        $progress = $this->getJson('/api/exercises/'.$this->bench.'/progress');
        self::assertSame(80.0, self::number($progress, 'sessions.0.topLoadKg'));
        self::assertSame(112.0, self::number($progress, 'sessions.0.estimatedOneRepMax'));
    }

    public function testOneWorkoutAtATimeAndFreeOnesGetExercisesOnTheGo(): void
    {
        $this->start(self::WORKOUT);
        $this->call('PUT', '/api/workouts/'.self::NEXT, ['startedAt' => $this->time('now')]);
        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('training.workout_in_progress');

        $this->call('DELETE', '/api/workouts/'.self::WORKOUT);
        self::assertResponseStatusCodeSame(204);
        $this->call('DELETE', '/api/workouts/'.self::WORKOUT);
        self::assertResponseStatusCodeSame(204, 'discarding again changes nothing');
        $this->start(self::WORKOUT);
        self::assertResponseStatusCodeSame(422, 'a discarded id does not start again');
        $free = $this->call('PUT', '/api/workouts/'.self::NEXT, ['startedAt' => $this->time('now')])->toArray();
        self::assertNull(self::at($free, 'programDay'));
        self::assertSame([], self::array($free, 'exercises'));

        $this->call('PUT', '/api/workouts/'.self::NEXT.'/exercises/0', ['exerciseId' => $this->plank]);
        self::assertResponseStatusCodeSame(204);
        $this->call('PUT', '/api/workouts/'.self::NEXT.'/exercises/0', ['exerciseId' => $this->plank]);
        self::assertResponseStatusCodeSame(204);
        $this->call('PUT', '/api/workouts/'.self::NEXT.'/exercises/0', ['exerciseId' => $this->bench]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('training.invalid_workout');
        $exercises = self::array($this->getJson('/api/workouts/'.self::NEXT), 'exercises');
        self::assertCount(1, $exercises);
        self::assertTrue(self::at($exercises, '0.added'));
    }

    public function testSetsMustMatchTheirExercise(): void
    {
        $this->start(self::WORKOUT);
        $set = '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000501';

        $this->call('PUT', $set, ['exerciseIndex' => 1, 'loadKg' => 20, 'reps' => 10, 'doneAt' => $this->time('now')]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('training.invalid_workout');

        $refused = $this->call('PUT', $set, ['kind' => 'giant', 'loadKg' => -1, 'reps' => 400, 'rir' => 11]);
        self::assertResponseStatusCodeSame(422);
        $paths = array_column(self::array($refused->toArray(false), 'violations'), 'propertyPath');
        foreach (['exerciseIndex', 'kind', 'loadKg', 'reps', 'rir', 'doneAt'] as $path) {
            self::assertContains($path, $paths);
        }

        $this->call('PUT', $set, ['exerciseIndex' => 0, 'loadKg' => 80, 'reps' => 8, 'doneAt' => $this->time('+2 hours')]);
        self::assertResponseStatusCodeSame(422, 'a time from the future');
    }

    public function testTheHistoryPagesByStartTime(): void
    {
        $this->start(self::WORKOUT);
        $this->call('POST', '/api/workouts/'.self::WORKOUT.'/finish', ['finishedAt' => $this->time('now')]);

        self::assertCount(1, $this->getJson('/api/workouts?before='.rawurlencode(new \DateTimeImmutable('+1 minute')->format('Y-m-d\TH:i:s.vP'))));
        self::assertSame([], $this->getJson('/api/workouts?before='.rawurlencode($this->time('-2 hours'))));
        $this->call('GET', '/api/workouts?before=hier');
        self::assertResponseStatusCodeSame(400);
    }

    public function testWorkoutsArePrivate(): void
    {
        $this->start(self::WORKOUT);
        $this->call('PUT', '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000501', ['exerciseIndex' => 0, 'loadKg' => 80, 'reps' => 8, 'doneAt' => $this->time('-30 minutes')]);
        $this->call('POST', '/api/workouts/'.self::WORKOUT.'/finish', ['finishedAt' => $this->time('now')]);
        $this->start(self::NEXT);

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        foreach ([
            ['GET', '/api/workouts/'.self::NEXT, null],
            ['POST', '/api/workouts/'.self::NEXT.'/finish', ['finishedAt' => $this->time('now')]],
            ['DELETE', '/api/workouts/'.self::NEXT, null],
            ['DELETE', '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000501', null],
            ['PUT', '/api/workouts/'.self::NEXT.'/exercises/2', ['exerciseId' => $this->plank]],
        ] as [$method, $uri, $body]) {
            $this->call($method, $uri, $body);
            self::assertResponseStatusCodeSame(404, $method.' '.$uri);
        }
        self::assertSame([], $this->getJson('/api/workouts/in-progress'), 'someone else\'s workout in progress is not mine');
        self::assertSame([], self::array($this->getJson('/api/exercises/'.$this->bench.'/progress'), 'sessions'), 'nor are their sessions');
        $this->call('PUT', '/api/workouts/'.self::WORKOUT.'/sets/0199a0b1-0000-7000-8000-000000000501', ['exerciseIndex' => 0, 'loadKg' => 80, 'reps' => 8, 'doneAt' => $this->time('now')]);
        self::assertResponseStatusCodeSame(404);
        $this->call('PUT', '/api/workouts/'.self::WORKOUT, ['startedAt' => $this->time('now')]);
        self::assertResponseStatusCodeSame(422, 'someone else\'s id');
        $this->call('GET', '/api/workouts/preview?programId='.$this->programId.'&day=0');
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->getJson('/api/workouts'), 'Alex has a finished workout, Sam none');
        $this->call('PUT', '/api/workouts/0199a0b1-0000-7000-8000-00000000d0aa', ['startedAt' => $this->time('now')]);
        self::assertResponseStatusCodeSame(200, 'one workout in progress per user, not for everyone');
    }

    /**
     * @return array<mixed>
     */
    private function start(string $id): array
    {
        return $this->call('PUT', '/api/workouts/'.$id, ['programId' => $this->programId, 'versionNumber' => 1, 'dayIndex' => 0, 'startedAt' => $this->time('-1 hour')])->toArray(false);
    }

    private function time(string $when): string
    {
        return new \DateTimeImmutable($when)->format(\DateTimeInterface::ATOM);
    }
}
