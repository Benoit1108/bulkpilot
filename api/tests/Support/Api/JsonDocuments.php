<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

/**
 * Reading the JSON the API answers: GET as an array, and typed values at a dotted path.
 */
trait JsonDocuments
{
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
