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
    use JsonDocuments;

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
}
