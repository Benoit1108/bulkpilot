<?php

declare(strict_types=1);

namespace App\Tests\Api\Security;

use App\Tests\Support\Api\TrainingApiTestCase;
use App\Tests\Support\Media\Images;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Who may call what, for every operation of the API as its OpenAPI spec lists them (spec §12,
 * §101): a new route is covered by default, and fails here until it says where it stands.
 *
 * - anonymous → 401, apart from the public operations below;
 * - another user's resource, named in the path → 404 (as if it did not exist);
 * - another user's resource, named in the query or the body (a food in a recipe, an exercise in a
 *   program) → refused, 404 or 422, and nothing of it is shown;
 * - a list, a summary or a search never holds another user's ids;
 * - a write sent from another site → 403 (CSRF, ADR-0008).
 */
final class AccessMatrixTest extends TrainingApiTestCase
{
    use ClockSensitiveTrait;

    /** Reachable signed out, and why. */
    private const array PUBLIC = [
        'GET /api/health' => 'the container and uptime checks',
        'GET /api/auth/providers' => 'the sign-in page lists the external providers',
        'POST /api/auth/register' => 'signing up',
        'POST /api/auth/sign-in' => 'signing in',
        'POST /api/auth/sign-in/second-factor' => 'the second step of a sign-in (its own pending state, 401 without it)',
        'POST /api/auth/sign-out' => 'signing out is idempotent, also when the session is already gone',
        'POST /api/auth/email-verification' => 'the emailed link, opened on any device',
        'POST /api/auth/password-reset-request' => 'a forgotten password',
        'POST /api/auth/password-reset' => 'the emailed reset link',
    ];

    /**
     * Routes under /api the OpenAPI spec does not list (plain controllers), and how a signed-out
     * caller is answered. API Platform's own (entry point, docs, contexts, errors) are left out.
     */
    private const array OUTSIDE_THE_SPEC = [
        // Starting and finishing a sign-in with an external provider: public by nature.
        'identity_external_start' => null,
        'identity_external_callback' => null,
        // Local storage's signed links (dev and test only): the session is required besides the signature.
        'media_local' => 401,
    ];

    /**
     * Operations naming one of the user's resources: each path parameter → the kind of resource
     * it names. Another user's one is not found.
     */
    private const array OWNED = [
        'GET /api/coach/recommendations/{id}' => ['id' => 'recommendation'],
        'POST /api/coach/recommendations/{id}/decision' => ['id' => 'recommendation'],
        'GET /api/exercises/{id}' => ['id' => 'exercise'],
        'PUT /api/exercises/{id}' => ['id' => 'exercise'],
        'DELETE /api/exercises/{id}' => ['id' => 'exercise'],
        'PUT /api/exercises/{id}/note' => ['id' => 'exercise'],
        'GET /api/exercises/{id}/progress' => ['id' => 'exercise'],
        'GET /api/exports/{id}' => ['id' => 'export'],
        'POST /api/exports/{id}/download' => ['id' => 'export'],
        'GET /api/foods/{id}' => ['id' => 'food'],
        'PUT /api/foods/{id}' => ['id' => 'food'],
        'DELETE /api/foods/{id}' => ['id' => 'food'],
        'DELETE /api/me/sessions/{id}' => ['id' => 'session'],
        'GET /api/notifications/{id}' => ['id' => 'notification'],
        'POST /api/notifications/{id}/read' => ['id' => 'notification'],
        'PUT /api/nutrition/entries/{id}' => ['id' => 'entry'],
        'DELETE /api/nutrition/entries/{id}' => ['id' => 'entry'],
        'DELETE /api/nutrition/favorite-meals/{id}' => ['id' => 'favorite'],
        'POST /api/nutrition/favorite-meals/{id}/log' => ['id' => 'favorite'],
        'GET /api/programs/{id}' => ['id' => 'program'],
        'PUT /api/programs/{id}' => ['id' => 'program'],
        'DELETE /api/programs/{id}' => ['id' => 'program'],
        'POST /api/programs/{id}/activate' => ['id' => 'program'],
        'POST /api/programs/{id}/deactivate' => ['id' => 'program'],
        'GET /api/progression/measurements/{day}' => ['day' => 'day'],
        'PUT /api/progression/measures/{key}' => ['key' => 'measure'],
        'DELETE /api/progression/measures/{key}' => ['key' => 'measure'],
        'GET /api/progression/photos/{id}' => ['id' => 'photo'],
        'DELETE /api/progression/photos/{id}' => ['id' => 'photo'],
        'GET /api/progression/weigh-ins/{day}' => ['day' => 'day'],
        'GET /api/recipes/{id}' => ['id' => 'recipe'],
        'PUT /api/recipes/{id}' => ['id' => 'recipe'],
        'DELETE /api/recipes/{id}' => ['id' => 'recipe'],
        'GET /api/workouts/{id}' => ['id' => 'workout'],
        'PUT /api/workouts/{id}' => ['id' => 'workout'],
        'DELETE /api/workouts/{id}' => ['id' => 'workout'],
        'POST /api/workouts/{id}/finish' => ['id' => 'workout'],
        'PUT /api/workouts/{workoutId}/exercises/{index}' => ['workoutId' => 'workout', 'index' => 'index'],
        'PUT /api/workouts/{workoutId}/sets/{id}' => ['workoutId' => 'workout', 'id' => 'set'],
        'DELETE /api/workouts/{workoutId}/sets/{id}' => ['workoutId' => 'workout', 'id' => 'set'],
    ];

    /**
     * The path names a day of the caller's own: another user's day is just the caller's, empty
     * (a write makes the caller's own, which the last test proves leaves the other user's alone).
     */
    private const array SCOPED_TO_THE_CALLER = [
        'GET /api/nutrition/days/{day}',
        'PUT /api/progression/measurements/{day}',
        'DELETE /api/progression/measurements/{day}',
        'PUT /api/progression/weigh-ins/{day}',
        'DELETE /api/progression/weigh-ins/{day}',
    ];

    /**
     * Operations taking ids in the query or the body (OpenAPI: a property or parameter named …Id):
     * each says how Sam sends one of Alex's. Paging cursors only bound a list of one's own.
     */
    private const array IDS_IN_QUERY_OR_BODY = [
        'GET /api/me/activity' => 'cursor',
        'GET /api/coach/recommendations' => 'cursor',
        'GET /api/notifications' => 'cursor',
        'POST /api/nutrition/entries' => 'food',
        'POST /api/nutrition/entries/batch' => 'food',
        'POST /api/programs' => 'exercise',
        'PUT /api/programs/{id}' => 'exercise',
        'POST /api/recipes' => 'food',
        'PUT /api/recipes/{id}' => 'food',
        'PUT /api/workouts/{id}' => 'program',
        'PUT /api/workouts/{workoutId}/exercises/{index}' => 'exercise',
        'GET /api/workouts/preview' => 'program',
    ];

    /** Kinds of resources shared by everyone (the food catalogue, the exercise library), or not ids. */
    private const array SHARED = ['day', 'index', 'bench', 'rice'];

    /** Another user's resource answered otherwise than 404, and why it tells nothing about it. */
    private const array NOT_FOUND_EXCEPTIONS = [
        // The device chooses workout ids (offline starts): a taken one is refused like one's own
        // discarded workout, without saying whose it is.
        'PUT /api/workouts/{id}' => 422,
    ];

    private const string WORKOUT = '0199a0b1-0000-7000-8000-0000000a0001';
    private const string SET = '0199a0b1-0000-7000-8000-0000000a0101';
    private const string ANY_ID = '0199a0b1-0000-7000-8000-00000000ffff';
    private const array FOOD = ['name' => 'Gainer maison', 'energyKcal' => 400, 'protein' => 20, 'carbohydrate' => 60, 'fat' => 8];
    private const array EXERCISE = ['name' => 'Pompes lestées', 'primaryMuscle' => 'chest', 'type' => 'compound', 'equipment' => 'bodyweight', 'measurement' => 'weight', 'repMin' => 8, 'repMax' => 12];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        self::mockTime('2026-09-30 10:00:00');
    }

    public function testEveryOperationSaysWhoMayCallIt(): void
    {
        $operations = $this->operations();
        foreach ($operations as $operation) {
            [, $path] = explode(' ', $operation, 2);
            if (isset(self::PUBLIC[$operation]) || !str_contains($path, '{')) {
                continue;
            }
            self::assertTrue(
                isset(self::OWNED[$operation]) || \in_array($operation, self::SCOPED_TO_THE_CALLER, true),
                \sprintf('%s names a resource: say which kind in OWNED (another user\'s one must be not found), or why it is the caller\'s own', $operation),
            );
        }
        $listed = [...array_keys(self::PUBLIC), ...array_keys(self::OWNED), ...self::SCOPED_TO_THE_CALLER, ...array_keys(self::IDS_IN_QUERY_OR_BODY)];
        self::assertSame([], array_values(array_diff($listed, $operations)), 'operations listed here that the API no longer has');

        $taking = array_keys(array_filter($this->spec(), static fn (array $ids): bool => [] !== $ids));
        sort($taking);
        $classified = array_keys(self::IDS_IN_QUERY_OR_BODY);
        sort($classified);
        self::assertSame($taking, $classified, 'an operation taking ids in its query or body says here how another user\'s one is sent');
    }

    public function testEveryOperationOfTheApiIsInTheSpec(): void
    {
        // An operation hidden from the spec (openapi: false) would escape every check above.
        $documented = $this->operations();
        $undocumented = [];
        foreach (self::getContainer()->get('router')->getRouteCollection()->all() as $name => $route) {
            // API Platform's own: the item routes it declares to build IRIs (they always answer 404)
            // and its error resources.
            if (null === $route->getDefault('_api_resource_class') || !str_starts_with($route->getPath(), '/api')
                || 'api_platform.action.not_exposed' === $route->getDefault('_controller')
                || 1 === preg_match('/^_?api_(errors|validation_errors)|genid/', $name)) {
                continue;
            }
            $path = str_replace('{._format}', '', $route->getPath());
            foreach ($route->getMethods() as $method) {
                $operation = $method.' '.$path;
                if (!\in_array($operation, $documented, true)) {
                    $undocumented[] = $operation;
                }
            }
        }
        self::assertSame([], $undocumented, 'every API Platform operation is in the OpenAPI spec');
    }

    public function testEveryRouteOutsideTheSpecIsKnown(): void
    {
        $routes = [];
        foreach (self::getContainer()->get('router')->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!str_starts_with($route->getPath(), '/api') || null !== $route->getDefault('_api_resource_class') || (\is_string($controller) && str_starts_with($controller, 'api_platform.'))) {
                continue;
            }
            $routes[] = $name;
        }
        sort($routes);
        $known = array_keys(self::OUTSIDE_THE_SPEC);
        sort($known);
        self::assertSame($known, $routes, 'a plain controller under /api says here who may call it');

        $this->call('GET', '/api/media/'.self::ANY_ID.'.webp?expires=9999999999&signature=x');
        self::assertResponseStatusCodeSame(self::OUTSIDE_THE_SPEC['media_local']);
    }

    public function testSignedOutEveryOperationButThePublicOnesIsRefused(): void
    {
        foreach ($this->operations() as $operation) {
            if (isset(self::PUBLIC[$operation])) {
                continue;
            }
            [$method, $path] = explode(' ', $operation, 2);
            $response = $this->call($method, self::anyValues($path), 'GET' === $method || 'DELETE' === $method ? null : []);
            self::assertSame(401, $response->getStatusCode(), $operation);
        }
    }

    public function testAWriteSentFromAnotherSiteIsRefusedBeforeAnythingRuns(): void
    {
        $this->registerVerified();

        foreach ($this->operations() as $operation) {
            [$method, $path] = explode(' ', $operation, 2);
            if ('GET' === $method) {
                continue;
            }
            $response = $this->call($method, self::anyValues($path), 'DELETE' === $method ? null : [], ['Origin' => 'https://evil.example']);
            self::assertSame(403, $response->getStatusCode(), $operation.' from another origin');
        }
        $this->call('POST', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'a brand new passphrase'], ['Sec-Fetch-Site' => 'cross-site']);
        self::assertResponseStatusCodeSame(403, 'a browser saying the request comes from another site');
        $this->call('POST', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'a brand new passphrase'], ['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin']);
        self::assertResponseStatusCodeSame(204, 'the app itself');
    }

    public function testAnotherUsersResourcesAreNotFoundAndLeftAlone(): void
    {
        $this->registerVerified();
        $resources = $this->alexUsesTheApp();
        $before = $this->alexsWorld($resources);

        $this->useDevice('sam');
        $this->registerVerified('sam@example.com');
        foreach (self::OWNED as $operation => $parameters) {
            [$method, $path] = explode(' ', $operation, 2);
            $uri = $path;
            foreach ($parameters as $name => $kind) {
                $uri = str_replace('{'.$name.'}', $resources[$kind], $uri);
            }
            $response = $this->call($method, $uri, $this->body($operation, $resources));
            self::assertSame(self::NOT_FOUND_EXCEPTIONS[$operation] ?? 404, $response->getStatusCode(), $operation.' on Alex\'s: '.$response->getContent(false));
        }
        foreach (self::SCOPED_TO_THE_CALLER as $operation) {
            [$method, $path] = explode(' ', $operation, 2);
            $response = $this->call($method, str_replace('{day}', $resources['day'], $path), $this->body($operation, $resources));
            self::assertLessThan(400, $response->getStatusCode(), $operation.' on Sam\'s own day');
        }

        $this->useDevice('laptop');
        self::assertSame($before, $this->alexsWorld($resources), 'nothing of Alex\'s changed');
    }

    public function testAnotherUsersResourcesInTheQueryOrTheBodyAreRefused(): void
    {
        $this->registerVerified();
        $resources = $this->alexUsesTheApp();
        $before = $this->alexsWorld($resources);

        $this->useDevice('sam');
        $this->registerVerified('sam@example.com');
        $at = static fn (string $when): string => new \DateTimeImmutable('2026-09-30 08:00:00 UTC')->modify($when)->format(\DATE_ATOM);
        // Before Sam's own workout is in progress (one at a time).
        $start = $this->call('PUT', '/api/workouts/'.self::ANY_ID, ['startedAt' => $at('+0 minutes'), 'programId' => $resources['program'], 'versionNumber' => 1, 'dayIndex' => 0]);
        self::assertContains($start->getStatusCode(), [404, 422], 'a workout from Alex\'s program: '.$start->getContent(false));
        $own = $this->samUsesTheApp();
        $attempts = [
            'POST /api/nutrition/entries' => ['POST', '/api/nutrition/entries', ['day' => '2026-09-30', 'slot' => 'dinner', 'foodId' => $resources['food'], 'grams' => 100]],
            'POST /api/nutrition/entries (recipe)' => ['POST', '/api/nutrition/entries', ['day' => '2026-09-30', 'slot' => 'dinner', 'recipeId' => $resources['recipe'], 'grams' => 100]],
            'POST /api/nutrition/entries/batch' => ['POST', '/api/nutrition/entries/batch', ['day' => '2026-09-30', 'slot' => 'dinner', 'items' => [['foodId' => $resources['food'], 'grams' => 100]]]],
            'POST /api/programs' => ['POST', '/api/programs', ['name' => 'Volé', 'days' => [['name' => 'A', 'blocks' => [['exercises' => [self::planned($resources['exercise'])]]]]]]],
            'PUT /api/programs/{id}' => ['PUT', '/api/programs/'.$own['program'], ['name' => 'Volé', 'days' => [['name' => 'A', 'blocks' => [['exercises' => [self::planned($resources['exercise'])]]]]]]],
            'POST /api/recipes' => ['POST', '/api/recipes', ['name' => 'Volé', 'servings' => 1, 'ingredients' => [['foodId' => $resources['food'], 'grams' => 100]]]],
            'PUT /api/recipes/{id}' => ['PUT', '/api/recipes/'.$own['recipe'], ['name' => 'Volé', 'servings' => 1, 'ingredients' => [['foodId' => $resources['food'], 'grams' => 100]]]],
            'PUT /api/workouts/{workoutId}/exercises/{index}' => ['PUT', '/api/workouts/'.$own['workout'].'/exercises/0', ['exerciseId' => $resources['exercise']]],
            'GET /api/workouts/preview' => ['GET', '/api/workouts/preview?programId='.$resources['program'].'&day=0', null],
        ];
        foreach ($attempts as $name => [$method, $uri, $body]) {
            $response = $this->call($method, $uri, $body);
            self::assertContains($response->getStatusCode(), [404, 422], $name.' with Alex\'s id: '.$response->getContent(false));
            // The refusal may repeat the id Sam sent; nothing else of Alex's.
            $this->assertHoldsNothingOf($resources, $response->getContent(false), $name, sent: $uri.json_encode($body));
        }
        foreach (array_keys(self::IDS_IN_QUERY_OR_BODY, 'cursor', true) as $operation) {
            [, $path] = explode(' ', $operation, 2);
            $response = $this->call('GET', $path.'?before=2099-01-01T00:00:00%2B00:00&beforeId='.$resources['notification']);
            self::assertSame(200, $response->getStatusCode(), $operation);
            $this->assertHoldsNothingOf($resources, $response->getContent(false), $operation.' paged on Alex\'s id');
        }

        $this->useDevice('laptop');
        self::assertSame($before, $this->alexsWorld($resources), 'nothing of Alex\'s changed');
    }

    public function testNoListSummaryOrSearchHoldsAnotherUsersIds(): void
    {
        $this->registerVerified();
        $resources = $this->alexUsesTheApp();

        $this->useDevice('sam');
        $this->registerVerified('sam@example.com');
        $read = 0;
        foreach ($this->spec() as $operation => $ids) {
            [$method, $path] = explode(' ', $operation, 2);
            if ('GET' !== $method || isset(self::PUBLIC[$operation]) || str_contains($path, '{')) {
                continue;
            }
            $response = $this->call('GET', $path.'?'.http_build_query($this->requiredQuery($operation, $resources)));
            self::assertLessThan(500, $response->getStatusCode(), $operation);
            if (200 === $response->getStatusCode()) {
                ++$read;
                $this->assertHoldsNothingOf($resources, $response->getContent(false), $operation);
            }
        }
        self::assertGreaterThan(30, $read, 'the lists, summaries and searches were read');

        // The caller's own day: Sam's, empty, not Alex's.
        $day = $this->call('GET', '/api/nutrition/days/2026-09-30');
        self::assertSame(200, $day->getStatusCode());
        $this->assertHoldsNothingOf($resources, $day->getContent(false), 'Sam\'s day');
        self::assertSame([], array_merge(...array_map(static fn (mixed $meal): array => \is_array($meal) && \is_array($meal['entries'] ?? null) ? $meal['entries'] : [], self::array($day->toArray(), 'meals'))), 'nothing logged on Sam\'s day');
    }

    /**
     * @param array<string, string> $resources
     */
    private function assertHoldsNothingOf(array $resources, string $content, string $where, string $sent = ''): void
    {
        foreach (['Gainer maison', 'Riz gainer', 'Pompes lestées', 'Hanches'] as $name) {
            self::assertStringNotContainsString($name, $content, \sprintf('%s shows a name of Alex\'s', $where));
        }
        foreach ($resources as $kind => $id) {
            if (!\in_array($kind, self::SHARED, true) && !str_contains($sent, $id)) {
                self::assertStringNotContainsString($id, $content, \sprintf('%s shows Alex\'s %s', $where, $kind));
            }
        }
    }

    /**
     * Values for the query parameters an operation requires (the device's today, a period…).
     *
     * @param array<string, string> $resources
     *
     * @return array<string, string>
     */
    private function requiredQuery(string $operation, array $resources): array
    {
        $values = ['today' => '2026-09-30', 'days' => '7', 'period' => '30d', 'q' => 'riz', 'day' => '0', 'programId' => self::ANY_ID];
        $query = [];
        foreach ($this->required[$operation] ?? [] as $name) {
            $query[$name] = $values[$name] ?? self::fail(\sprintf('%s requires "%s": give it a value here', $operation, $name));
        }

        return $query;
    }

    /** @var array<string, list<string>> required query parameters by operation, read from the spec */
    private array $required = [];

    /**
     * Sam's own program, recipe and workout, to send Alex's ids into.
     *
     * @return array{program: string, recipe: string, workout: string}
     */
    private function samUsesTheApp(): array
    {
        $bench = $this->exerciseId('developpe couche barre');
        $rice = $this->foodId('riz');
        $program = self::string($this->call('POST', '/api/programs', ['name' => 'Sam', 'days' => [['name' => 'A', 'blocks' => [['exercises' => [self::planned($bench)]]]]]])->toArray(), 'id');
        $recipe = self::string($this->call('POST', '/api/recipes', ['name' => 'Riz', 'servings' => 1, 'ingredients' => [['foodId' => $rice, 'grams' => 100]]])->toArray(), 'id');
        $workout = '0199a0b1-0000-7000-8000-0000000b0001';
        $this->call('PUT', '/api/workouts/'.$workout, ['startedAt' => new \DateTimeImmutable('2026-09-30 07:00:00 UTC')->format(\DATE_ATOM)]);
        self::assertResponseStatusCodeSame(200);

        return ['program' => $program, 'recipe' => $recipe, 'workout' => $workout];
    }

    /**
     * The ids each operation takes in its query or body, by operation; also records the required
     * query parameters.
     *
     * @return array<string, list<string>>
     */
    private function spec(): array
    {
        $spec = $this->client->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']])->toArray();
        $components = $spec['components'] ?? [];
        $schemas = \is_array($components) ? ($components['schemas'] ?? []) : [];
        self::assertIsArray($schemas);
        $paths = $spec['paths'] ?? null;
        self::assertIsArray($paths);
        $operations = [];
        foreach ($paths as $path => $methods) {
            self::assertIsArray($methods);
            foreach ($methods as $method => $operation) {
                if (!\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true) || !\is_array($operation)) {
                    continue;
                }
                $name = strtoupper($method).' '.$path;
                $ids = [];
                foreach (\is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [] as $parameter) {
                    if (!\is_array($parameter) || 'query' !== ($parameter['in'] ?? null) || !\is_string($parameter['name'] ?? null)) {
                        continue;
                    }
                    if (1 === preg_match('/Ids?$/', $parameter['name'])) {
                        $ids[] = $parameter['name'];
                    }
                    if (true === ($parameter['required'] ?? false)) {
                        $this->required[$name][] = $parameter['name'];
                    }
                }
                $body = $operation['requestBody'] ?? [];
                $content = \is_array($body) ? ($body['content'] ?? []) : [];
                foreach (\is_array($content) ? $content : [] as $type) {
                    $ids = [...$ids, ...self::idFields(\is_array($type) ? ($type['schema'] ?? []) : [], $schemas, [])];
                }
                $operations[$name] = array_values(array_unique($ids));
            }
        }

        return $operations;
    }

    /**
     * Properties named …Id or …Ids anywhere in a schema (references, items and compositions followed).
     *
     * @param array<mixed> $schemas
     * @param list<string> $seen
     *
     * @return list<string>
     */
    private static function idFields(mixed $schema, array $schemas, array $seen): array
    {
        if (!\is_array($schema)) {
            return [];
        }
        if (\is_string($schema['$ref'] ?? null)) {
            $ref = substr($schema['$ref'], (int) strrpos($schema['$ref'], '/') + 1);

            return \in_array($ref, $seen, true) ? [] : self::idFields($schemas[$ref] ?? [], $schemas, [...$seen, $ref]);
        }
        $fields = [];
        foreach (['allOf', 'anyOf', 'oneOf'] as $composition) {
            foreach (\is_array($schema[$composition] ?? null) ? $schema[$composition] : [] as $part) {
                $fields = [...$fields, ...self::idFields($part, $schemas, $seen)];
            }
        }
        $fields = [...$fields, ...self::idFields($schema['items'] ?? null, $schemas, $seen)];
        foreach (\is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $property => $inner) {
            if (\is_string($property) && 1 === preg_match('/Ids?$/', $property)) {
                $fields[] = $property;
            }
            $fields = [...$fields, ...self::idFields($inner, $schemas, $seen)];
        }

        return $fields;
    }

    /**
     * @return list<string> "METHOD /path" of every operation the API documents
     */
    private function operations(): array
    {
        $spec = $this->client->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']])->toArray();
        $paths = $spec['paths'] ?? null;
        self::assertIsArray($paths);
        $operations = [];
        foreach ($paths as $path => $methods) {
            self::assertIsArray($methods);
            foreach (array_keys($methods) as $method) {
                if (\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $operations[] = strtoupper($method).' '.$path;
                }
            }
        }
        self::assertGreaterThan(100, \count($operations), 'the spec is read');

        return $operations;
    }

    private static function anyValues(string $path): string
    {
        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $parameter): string => match ($parameter[1]) {
            'day' => '2026-09-29',
            'index' => '0',
            default => self::ANY_ID,
        }, $path);
    }

    /**
     * Alex's resources of every kind, by kind.
     *
     * @return array<string, string>
     */
    private function alexUsesTheApp(): array
    {
        $at = static fn (string $when): string => new \DateTimeImmutable('2026-09-29 18:00:00 UTC')->modify($when)->format(\DATE_ATOM);
        $this->call('PUT', '/api/progression/weigh-ins/2026-09-29', ['weightKg' => 80.5]);
        self::assertResponseStatusCodeSame(200);
        $measure = self::string($this->call('POST', '/api/progression/measures', ['name' => 'Hanches'])->toArray(), 'key');
        $this->call('PUT', '/api/progression/measurements/2026-09-29', ['centimetres' => ['waist' => 80, $measure => 95]]);
        self::assertResponseStatusCodeSame(200);
        $photo = $this->client->request('POST', '/api/progression/photos', [
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'multipart/form-data'],
            'extra' => ['parameters' => ['day' => '2026-09-29', 'pose' => 'front'], 'files' => ['file' => new UploadedFile(Images::png(40, 60), 'IMG_1.png', 'image/png', null, true)]],
        ]);
        self::assertResponseStatusCodeSame(201);
        $rice = $this->foodId('riz');
        $day = $this->logFood($rice, 150, 'lunch', '2026-09-30');
        $food = self::string($this->call('POST', '/api/foods', self::FOOD)->toArray(), 'id');
        $recipe = self::string($this->call('POST', '/api/recipes', ['name' => 'Riz gainer', 'servings' => 2, 'ingredients' => [['foodId' => $rice, 'grams' => 200], ['foodId' => $food, 'grams' => 50]]])->toArray(), 'id');
        $favorite = self::string($this->call('POST', '/api/nutrition/favorite-meals', ['name' => 'Midi', 'day' => '2026-09-30', 'slot' => 'lunch'])->toArray(), 'id');
        $bench = $this->exerciseId('developpe couche barre');
        $exercise = self::string($this->call('POST', '/api/exercises', self::EXERCISE)->toArray(), 'id');
        $program = self::string($this->call('POST', '/api/programs', ['name' => 'Full', 'days' => [['name' => 'A', 'blocks' => [['exercises' => [self::planned($bench)]]]]]])->toArray(), 'id');
        // Two sessions, the second heavier: a record, and so a notification.
        $earlier = substr(self::WORKOUT, 0, -1).'0';
        foreach ([[$earlier, '+0 minutes', 60, '0199a0b1-0000-7000-8000-0000000a0100'], [self::WORKOUT, '+14 hours', 70, self::SET]] as [$workout, $start, $load, $set]) {
            $this->call('PUT', '/api/workouts/'.$workout, ['startedAt' => $at($start)]);
            self::assertResponseStatusCodeSame(200);
            $this->call('PUT', '/api/workouts/'.$workout.'/exercises/0', ['exerciseId' => $bench]);
            $this->call('PUT', '/api/workouts/'.$workout.'/sets/'.$set, ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => $load, 'reps' => 8, 'doneAt' => $at($start.' +10 minutes')]);
            $this->call('POST', '/api/workouts/'.$workout.'/finish', ['finishedAt' => $at($start.' +45 minutes')]);
            self::assertResponseStatusCodeSame(200);
        }
        $this->call('PUT', '/api/notifications/settings', ['categories' => ['low_calories' => true, 'workout_not_started' => true, 'missing_weigh_in' => true, 'goal_reached' => true, 'record' => true, 'weekly_report' => true], 'quietFrom' => null, 'quietTo' => null]);
        $this->call('POST', '/api/notifications/check');
        self::assertResponseStatusCodeSame(204);
        $coach = $this->call('POST', '/api/coach/analysis', ['today' => '2026-09-30'])->toArray();
        $export = self::string($this->call('POST', '/api/exports', [])->toArray(), 'id');

        return [
            'day' => '2026-09-29',
            'measure' => $measure,
            'photo' => self::string($photo->toArray(), 'id'),
            'entry' => self::entryOf($day),
            'food' => $food,
            'recipe' => $recipe,
            'favorite' => $favorite,
            'exercise' => $exercise,
            'program' => $program,
            'workout' => self::WORKOUT,
            'set' => self::SET,
            'index' => '0',
            'notification' => self::string($this->getJson('/api/notifications'), '0.id'),
            'recommendation' => self::string($coach, 'recommendations.0.id'),
            'export' => $export,
            'session' => self::string($this->getJson('/api/me/sessions'), '0.id'),
            'bench' => $bench,
            'rice' => $rice,
        ];
    }

    /**
     * What Alex sees of each resource, to compare before and after Sam's attempts.
     *
     * @param array<string, string> $resources
     *
     * @return array<string, mixed>
     */
    private function alexsWorld(array $resources): array
    {
        $world = [];
        foreach ([
            'weigh-in' => '/api/progression/weigh-ins/'.$resources['day'],
            'measurements' => '/api/progression/measurements/'.$resources['day'],
            'measures' => '/api/progression/measures',
            'photo' => '/api/progression/photos/'.$resources['photo'],
            'day' => '/api/nutrition/days/2026-09-30',
            'food' => '/api/foods/'.$resources['food'],
            'recipe' => '/api/recipes/'.$resources['recipe'],
            'favorites' => '/api/nutrition/favorite-meals',
            'exercise' => '/api/exercises/'.$resources['exercise'],
            'program' => '/api/programs/'.$resources['program'],
            'workout' => '/api/workouts/'.$resources['workout'],
            'notification' => '/api/notifications/'.$resources['notification'],
            'recommendation' => '/api/coach/recommendations/'.$resources['recommendation'],
            'export' => '/api/exports/'.$resources['export'],
            'sessions' => '/api/me/sessions',
        ] as $name => $uri) {
            $response = $this->call('GET', $uri);
            self::assertSame(200, $response->getStatusCode(), $name);
            $world[$name] = self::withoutSignedLinks($response->toArray());
        }

        return $world;
    }

    /**
     * Photo links are signed anew on every read; everything else must be the same.
     */
    private static function withoutSignedLinks(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = \in_array($key, ['url', 'thumbnailUrl', 'downloadUrl', 'expiresAt'], true) ? null : self::withoutSignedLinks($item);
        }

        return $value;
    }

    /**
     * @param array<mixed> $day
     */
    private static function entryOf(array $day): string
    {
        foreach (self::array($day, 'meals') as $meal) {
            $entries = \is_array($meal) ? ($meal['entries'] ?? []) : [];
            if (\is_array($entries) && [] !== $entries) {
                return self::string($entries, '0.id');
            }
        }
        self::fail('the logged entry is in the day');
    }

    /**
     * A valid body for the write: the refusal must come from whose the resource is, not from the input.
     *
     * @param array<string, string> $resources
     *
     * @return array<string, mixed>|null
     */
    private function body(string $operation, array $resources): ?array
    {
        $at = static fn (string $when): string => new \DateTimeImmutable('2026-09-30 08:00:00 UTC')->modify($when)->format(\DATE_ATOM);

        return match ($operation) {
            'POST /api/coach/recommendations/{id}/decision' => ['decision' => 'accept', 'today' => '2026-09-30'],
            'PUT /api/exercises/{id}' => self::EXERCISE,
            'PUT /api/exercises/{id}/note' => ['text' => 'x'],
            'PUT /api/foods/{id}' => self::FOOD,
            'PUT /api/nutrition/entries/{id}' => ['day' => '2026-09-30', 'slot' => 'dinner', 'grams' => 200],
            'POST /api/nutrition/favorite-meals/{id}/log' => ['day' => '2026-09-30', 'slot' => 'dinner'],
            'PUT /api/programs/{id}' => ['name' => 'Volé', 'days' => [['name' => 'A', 'blocks' => [['exercises' => [self::planned($resources['bench'])]]]]]],
            'PUT /api/progression/measures/{key}' => ['name' => 'Volé'],
            'PUT /api/recipes/{id}' => ['name' => 'Volé', 'servings' => 1, 'ingredients' => [['foodId' => $resources['rice'], 'grams' => 100]]],
            'PUT /api/workouts/{id}' => ['startedAt' => $at('+0 minutes')],
            'POST /api/workouts/{id}/finish' => ['finishedAt' => $at('+40 minutes')],
            'PUT /api/workouts/{workoutId}/exercises/{index}' => ['exerciseId' => $resources['bench']],
            'PUT /api/workouts/{workoutId}/sets/{id}' => ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => 100, 'reps' => 1, 'doneAt' => $at('+10 minutes')],
            'PUT /api/progression/measurements/{day}' => ['centimetres' => ['waist' => 60]],
            'PUT /api/progression/weigh-ins/{day}' => ['weightKg' => 60],
            default => str_starts_with($operation, 'POST ') ? [] : null,
        };
    }
}
