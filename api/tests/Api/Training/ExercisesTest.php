<?php

declare(strict_types=1);

namespace App\Tests\Api\Training;

use App\Tests\Support\Api\TrainingApiTestCase;

final class ExercisesTest extends TrainingApiTestCase
{
    public function testTheLibraryIsThereToSearch(): void
    {
        $this->register();

        $all = $this->getJson('/api/exercises');
        self::assertGreaterThanOrEqual(80, \count($all));

        $bench = $this->getJson('/api/exercises?q=developpe%20couche%20barre');
        self::assertSame('Développé couché à la barre', self::string($bench, '0.name'));
        self::assertSame(['source' => 'library', 'primaryMuscle' => 'chest', 'measurement' => 'weight', 'libraryKey' => 'barbell_bench_press', 'note' => null, 'editable' => false], array_intersect_key(self::array($bench, '0'), ['source' => 1, 'primaryMuscle' => 1, 'measurement' => 1, 'libraryKey' => 1, 'note' => 1, 'editable' => 1]));
        self::assertSame(['triceps', 'front_delts'], self::array($bench, '0.secondaryMuscles'));

        $calves = $this->getJson('/api/exercises?muscle=calves');
        self::assertNotEmpty($calves);
        foreach ($calves as $exercise) {
            self::assertContains('calves', [self::string($exercise, 'primaryMuscle'), ...self::array($exercise, 'secondaryMuscles')]);
        }
        self::assertSame([], $this->getJson('/api/exercises?q=zzzz'));
        $this->call('GET', '/api/exercises?muscle=inconnu');
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnOwnExerciseIsCreatedEditedAndDeleted(): void
    {
        $this->register();
        $body = ['name' => 'Rowing Yates', 'primaryMuscle' => 'upper_back', 'secondaryMuscles' => ['lats', 'biceps'], 'type' => 'compound', 'equipment' => 'barbell', 'measurement' => 'weight', 'repMin' => 8, 'repMax' => 12, 'targetRir' => 2, 'restSeconds' => 150, 'tempo' => '2011', 'description' => 'Buste à 30°', 'mediaUrl' => 'https://example.org/yates'];

        $created = $this->call('POST', '/api/exercises', $body)->toArray();
        self::assertResponseStatusCodeSame(201);
        $id = self::string($created, 'id');
        self::assertSame(['source' => 'custom', 'mediaUrl' => 'https://example.org/yates', 'libraryKey' => null, 'tempo' => '2-0-1-1', 'editable' => true], array_intersect_key($created, ['source' => 1, 'mediaUrl' => 1, 'libraryKey' => 1, 'tempo' => 1, 'editable' => 1]));
        self::assertSame('Rowing Yates', self::string($this->getJson('/api/exercises'), '0.name'), 'own ones first');

        $edited = $this->call('PUT', '/api/exercises/'.$id, ['name' => 'Rowing Yates supination', 'tempo' => null] + $body)->toArray();
        self::assertSame(['Rowing Yates supination', null], [$edited['name'], $edited['tempo']]);

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        $this->call('GET', '/api/exercises/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('training.exercise_not_found');
        self::assertNotContains($id, array_column($this->getJson('/api/exercises?q=yates'), 'id'));

        foreach ([['PUT', '/api/exercises/'.$id, $body], ['PUT', '/api/exercises/'.$id.'/note', ['text' => 'x']], ['GET', '/api/exercises/'.$id.'/progress', null], ['DELETE', '/api/exercises/'.$id, null]] as [$method, $uri, $payload]) {
            $this->call($method, $uri, $payload);
            self::assertResponseStatusCodeSame(404, $method.' '.$uri);
        }

        $this->useDevice('laptop');
        $this->call('DELETE', '/api/exercises/'.$id);
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/exercises/'.$id);
        self::assertResponseStatusCodeSame(404);
        $this->call('GET', '/api/exercises/pas-un-id');
        self::assertResponseStatusCodeSame(404);
    }

    public function testLibraryExercisesAreReadOnly(): void
    {
        $this->register();
        $bench = $this->exerciseId('developpe couche barre');

        $this->call('PUT', '/api/exercises/'.$bench, ['name' => 'Mon développé', 'primaryMuscle' => 'chest', 'repMin' => 8, 'repMax' => 12]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('training.invalid_exercise');
        $this->call('DELETE', '/api/exercises/'.$bench);
        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidExercisesAreRefusedFieldByField(): void
    {
        $this->register();

        $refused = $this->call('POST', '/api/exercises', ['name' => ' ', 'primaryMuscle' => 'wings', 'secondaryMuscles' => ['fins'], 'type' => 'other', 'measurement' => 'laps', 'repMin' => 12, 'repMax' => 8, 'targetRir' => 11, 'restSeconds' => 901, 'mediaUrl' => 'http://example.org']);
        self::assertResponseStatusCodeSame(422);
        $paths = array_column(self::array($refused->toArray(false), 'violations'), 'propertyPath');
        foreach (['name', 'primaryMuscle', 'secondaryMuscles[0]', 'type', 'measurement', 'repMax', 'targetRir', 'restSeconds', 'mediaUrl'] as $path) {
            self::assertContains($path, $paths);
        }

        $this->call('POST', '/api/exercises', ['name' => 'Tirage', 'primaryMuscle' => 'lats', 'repMin' => 8, 'repMax' => 12, 'tempo' => 'lent']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('training.invalid_prescription');
    }

    public function testANoteIsPersonalAndShownWithTheExercise(): void
    {
        $this->register();
        $bench = $this->exerciseId('developpe couche barre');

        $noted = $this->call('PUT', '/api/exercises/'.$bench.'/note', ['text' => 'Omoplates serrées'])->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Omoplates serrées', self::string($noted, 'note'));
        self::assertSame('Omoplates serrées', self::string($this->getJson('/api/exercises?q=developpe%20couche%20barre'), '0.note'));

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        self::assertNull(self::at($this->getJson('/api/exercises/'.$bench), 'note'));

        $this->useDevice('laptop');
        self::assertNull(self::at($this->call('PUT', '/api/exercises/'.$bench.'/note', ['text' => ''])->toArray(), 'note'));
        $this->call('PUT', '/api/exercises/'.$bench.'/note', ['text' => str_repeat('a', 501)]);
        self::assertResponseStatusCodeSame(422);
    }
}
