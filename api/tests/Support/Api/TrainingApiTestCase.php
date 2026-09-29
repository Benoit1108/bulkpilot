<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

use App\Shared\Application\Bus\Command\CommandBus;
use App\Training\Application\Library\ImportExerciseLibrary;

/**
 * The exercise library imported (the JSON helpers come from the nutrition base).
 */
abstract class TrainingApiTestCase extends NutritionApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::getContainer()->get(CommandBus::class)->dispatch(new ImportExerciseLibrary());
    }

    protected function exerciseId(string $query): string
    {
        return self::string($this->getJson('/api/exercises?q='.rawurlencode($query)), '0.id');
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected static function planned(string $exerciseId, array $override = []): array
    {
        return $override + ['exerciseId' => $exerciseId, 'sets' => 3, 'repMin' => 8, 'repMax' => 12, 'targetRir' => 2, 'restSeconds' => 120];
    }
}
