<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

use App\Nutrition\Application\Catalogue\ImportCatalogue;
use App\Shared\Application\Bus\Command\CommandBus;

/**
 * Signed-in user with the CIQUAL excerpt imported.
 */
abstract class NutritionApiTestCase extends IdentityApiTestCase
{
    protected string $today;

    protected function setUp(): void
    {
        parent::setUp();
        self::getContainer()->get(CommandBus::class)->dispatch(new ImportCatalogue(__DIR__.'/../../Fixtures/ciqual'));
        $this->today = new \DateTimeImmutable()->format('Y-m-d');
    }

    protected function foodId(string $query): string
    {
        return self::string($this->call('GET', '/api/foods?q='.rawurlencode($query))->toArray(), '0.id');
    }

    /**
     * @return array<mixed> the day the food was logged in
     */
    protected function logFood(string $foodId, float $grams, string $slot = 'lunch', ?string $day = null): array
    {
        $response = $this->call('POST', '/api/nutrition/entries', ['foodId' => $foodId, 'day' => $day ?? $this->today, 'slot' => $slot, 'grams' => $grams]);
        self::assertResponseStatusCodeSame(201);

        return $response->toArray();
    }

    /**
     * @return array<mixed>
     */
    protected function getJson(string $uri): array
    {
        return $this->call('GET', $uri)->toArray();
    }

    /**
     * Value at a dotted path of a decoded JSON document ("meals.1.entries.0.id").
     */
    protected static function at(mixed $json, string $path): mixed
    {
        foreach ('' === $path ? [] : explode('.', $path) as $key) {
            self::assertIsArray($json, $path);
            self::assertArrayHasKey($key, $json, $path);
            $json = $json[$key];
        }

        return $json;
    }

    protected static function string(mixed $json, string $path): string
    {
        $value = self::at($json, $path);
        self::assertIsString($value, $path);

        return $value;
    }

    protected static function int(mixed $json, string $path): int
    {
        $value = self::at($json, $path);
        self::assertIsInt($value, $path);

        return $value;
    }

    protected static function number(mixed $json, string $path): float
    {
        $value = self::at($json, $path);
        self::assertTrue(\is_int($value) || \is_float($value), $path);

        return (float) $value;
    }

    /**
     * @return array<mixed>
     */
    protected static function array(mixed $json, string $path = ''): array
    {
        $value = self::at($json, $path);
        self::assertIsArray($value, $path);

        return $value;
    }
}
