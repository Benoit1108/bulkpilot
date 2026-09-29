<?php

declare(strict_types=1);

namespace App\Tests\Api\Training;

use App\Tests\Support\Api\TrainingApiTestCase;

final class ProgramsTest extends TrainingApiTestCase
{
    public function testAProgramIsCreatedWithSupersetsAndBecomesActive(): void
    {
        $this->register();
        $bench = $this->exerciseId('developpe couche barre');
        $raise = $this->exerciseId('elevations laterales halteres');
        $pushdown = $this->exerciseId('extension triceps poulie');

        $program = $this->call('POST', '/api/programs', ['name' => 'PPL Masse', 'days' => [
            ['name' => 'Push', 'blocks' => [
                ['exercises' => [self::planned($bench, ['sets' => 4, 'repMin' => 6, 'repMax' => 10, 'restSeconds' => 180, 'warmupSets' => 2, 'strategy' => 'linear', 'incrementKg' => 2.5, 'tempo' => '3-1-1-0', 'notes' => 'Pause sur la poitrine'])]],
                ['exercises' => [self::planned($raise, ['repMin' => 12, 'repMax' => 20, 'restSeconds' => 0]), self::planned($pushdown, ['dropSet' => true])]],
            ]],
        ]])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['name' => 'PPL Masse', 'active' => true, 'currentVersion' => 1, 'version' => 1], array_intersect_key($program, ['name' => 1, 'active' => 1, 'currentVersion' => 1, 'version' => 1]));
        self::assertSame(['exerciseId' => $bench, 'name' => 'Développé couché à la barre', 'measurement' => 'weight', 'available' => true, 'sets' => 4, 'warmupSets' => 2, 'repMin' => 6, 'repMax' => 10, 'targetRir' => 2, 'restSeconds' => 180, 'tempo' => '3-1-1-0', 'strategy' => 'linear', 'incrementKg' => 2.5, 'dropSet' => false, 'notes' => 'Pause sur la poitrine'], self::array($program, 'days.0.blocks.0.exercises.0'));
        self::assertCount(2, self::array($program, 'days.0.blocks.1.exercises'), 'a superset');
        self::assertTrue(self::at($program, 'days.0.blocks.1.exercises.1.dropSet'));
        self::assertSame([1], array_column(self::array($program, 'versions'), 'number'));

        $list = $this->getJson('/api/programs');
        self::assertSame(['PPL Masse'], array_column($list, 'name'));
        self::assertSame([], self::array($list, '0.versions'));
    }

    public function testEditingTheDaysRecordsAVersionAndKeepsTheOldOne(): void
    {
        $this->register();
        $bench = $this->exerciseId('developpe couche barre');
        $row = $this->exerciseId('rowing barre');
        $days = [['name' => 'Full', 'blocks' => [['exercises' => [self::planned($bench)]]]]];
        $id = self::string($this->call('POST', '/api/programs', ['name' => 'Full Body', 'days' => $days])->toArray(), 'id');

        $renamed = $this->call('PUT', '/api/programs/'.$id, ['name' => 'Full Body A', 'days' => $days])->toArray();
        self::assertSame(['Full Body A', 1], [$renamed['name'], $renamed['currentVersion']]);

        $days[0]['blocks'][] = ['exercises' => [self::planned($row)]];
        $revised = $this->call('PUT', '/api/programs/'.$id, ['name' => 'Full Body A', 'days' => $days])->toArray();
        self::assertSame([2, 2], [$revised['currentVersion'], $revised['version']]);
        self::assertSame([2, 1], array_column(self::array($revised, 'versions'), 'number'));

        $first = $this->getJson('/api/programs/'.$id.'?version=1');
        self::assertSame([1, 2], [$first['version'], $first['currentVersion']]);
        self::assertCount(1, self::array($first, 'days.0.blocks'));
        $this->call('GET', '/api/programs/'.$id.'?version=9');
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('training.program_not_found');
    }

    public function testOneProgramIsActiveAtATime(): void
    {
        $this->register();
        $days = [['name' => 'Full', 'blocks' => [['exercises' => [self::planned($this->exerciseId('squat barre'))]]]]];
        $ppl = self::string($this->call('POST', '/api/programs', ['name' => 'PPL', 'days' => $days])->toArray(), 'id');
        $holidays = $this->call('POST', '/api/programs', ['name' => 'Vacances', 'days' => $days])->toArray();
        self::assertFalse(self::at($holidays, 'active'));

        $active = $this->call('POST', '/api/programs/'.self::string($holidays, 'id').'/activate')->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertTrue(self::at($active, 'active'));
        self::assertSame(['Vacances' => true, 'PPL' => false], array_column($this->getJson('/api/programs'), 'active', 'name'));

        $this->call('POST', '/api/programs/'.self::string($holidays, 'id').'/deactivate');
        self::assertSame(['PPL' => false, 'Vacances' => false], array_column($this->getJson('/api/programs'), 'active', 'name'));

        $this->call('DELETE', '/api/programs/'.$ppl);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Vacances'], array_column($this->getJson('/api/programs'), 'name'));
        $this->call('POST', '/api/programs/'.$ppl.'/activate');
        self::assertResponseStatusCodeSame(404);
    }

    public function testProgramsArePrivate(): void
    {
        $this->register();
        $id = self::string($this->call('POST', '/api/programs', ['name' => 'PPL', 'days' => [['name' => 'Push', 'blocks' => [['exercises' => [self::planned($this->exerciseId('developpe couche barre'))]]]]]])->toArray(), 'id');
        $mine = self::string($this->call('POST', '/api/exercises', ['name' => 'Curl araignée', 'primaryMuscle' => 'biceps', 'repMin' => 10, 'repMax' => 15])->toArray(), 'id');

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        self::assertSame([], $this->getJson('/api/programs'));
        foreach ([['GET', '/api/programs/'.$id], ['GET', '/api/programs/'.$id.'?version=1'], ['PUT', '/api/programs/'.$id], ['POST', '/api/programs/'.$id.'/activate'], ['POST', '/api/programs/'.$id.'/deactivate'], ['DELETE', '/api/programs/'.$id]] as [$method, $uri]) {
            $this->call($method, $uri, 'PUT' === $method ? ['name' => 'Volé', 'days' => [['name' => 'Push', 'blocks' => [['exercises' => [self::planned($this->exerciseId('developpe couche barre'))]]]]]] : null);
            self::assertResponseStatusCodeSame(404, $method.' '.$uri);
        }

        $this->call('POST', '/api/programs', ['name' => 'Avec l’exercice d’Alex', 'days' => [['name' => 'Pull', 'blocks' => [['exercises' => [self::planned($mine)]]]]]]);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('training.exercise_not_found');
    }

    public function testOnlyARealChangeOfTheDaysMakesAVersion(): void
    {
        $this->register();
        $bench = $this->exerciseId('developpe couche barre');
        $days = [['name' => 'Push', 'blocks' => [['exercises' => [self::planned($bench, ['tempo' => '3110', 'notes' => ' Pause ', 'incrementKg' => 1.25, 'warmupSets' => 2, 'dropSet' => true, 'targetRir' => null])]]]]];
        $id = self::string($this->call('POST', '/api/programs', ['name' => 'Push', 'days' => $days])->toArray(), 'id');

        self::assertSame(1, self::int($this->call('PUT', '/api/programs/'.$id, ['name' => 'Push', 'days' => $days])->toArray(), 'currentVersion'), 'the same days, written differently');
        $days[0]['blocks'][0]['exercises'][0]['notes'] = 'Pause 1 s';
        self::assertSame(2, self::int($this->call('PUT', '/api/programs/'.$id, ['name' => 'Push', 'days' => $days])->toArray(), 'currentVersion'));
        $days[0]['blocks'][0]['exercises'][0]['tempo'] = '3-1-2-0';
        self::assertSame(3, self::int($this->call('PUT', '/api/programs/'.$id, ['name' => 'Push', 'days' => $days])->toArray(), 'currentVersion'));
    }

    public function testInvalidProgramsAreRefused(): void
    {
        $this->register();
        $bench = $this->exerciseId('developpe couche barre');

        $refused = $this->call('POST', '/api/programs', ['name' => '', 'days' => []]);
        self::assertResponseStatusCodeSame(422);
        $paths = array_column(self::array($refused->toArray(false), 'violations'), 'propertyPath');
        self::assertContains('name', $paths);
        self::assertContains('days', $paths);

        $refused = $this->call('POST', '/api/programs', ['name' => 'PPL', 'days' => [['name' => 'Push', 'blocks' => [['exercises' => [self::planned($bench, ['sets' => 0, 'repMin' => 12, 'repMax' => 8, 'strategy' => 'magic', 'incrementKg' => 50]), self::planned('pas-un-id')]]]]]]);
        self::assertResponseStatusCodeSame(422);
        $paths = array_column(self::array($refused->toArray(false), 'violations'), 'propertyPath');
        foreach (['days[0].blocks[0].exercises[0].sets', 'days[0].blocks[0].exercises[0].repMax', 'days[0].blocks[0].exercises[0].strategy', 'days[0].blocks[0].exercises[0].incrementKg', 'days[0].blocks[0].exercises[1].exerciseId'] as $path) {
            self::assertContains($path, $paths);
        }

        $day = ['name' => 'Push', 'blocks' => [['exercises' => [self::planned($bench)]]]];
        $this->call('POST', '/api/programs', ['name' => 'PPL', 'days' => [$day, $day]]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('training.invalid_program');
    }
}
