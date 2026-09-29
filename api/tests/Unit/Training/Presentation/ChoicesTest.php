<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Presentation;

use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Presentation\Api\Exercise\ExerciseChoices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChoicesTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<\BackedEnum>, list<string>}>
     */
    public static function choices(): iterable
    {
        yield 'muscles' => [Muscle::class, ExerciseChoices::MUSCLES];
        yield 'equipment' => [Equipment::class, ExerciseChoices::EQUIPMENT];
        yield 'types' => [ExerciseType::class, ExerciseChoices::TYPES];
        yield 'measurements' => [Measurement::class, ExerciseChoices::MEASUREMENTS];
        yield 'strategies' => [ProgressionStrategy::class, ExerciseChoices::STRATEGIES];
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @param list<string>              $choices
     */
    #[DataProvider('choices')]
    public function testTheApiAcceptsExactlyTheDomainCodes(string $enum, array $choices): void
    {
        self::assertSame(array_map(static fn (\BackedEnum $case): string|int => $case->value, $enum::cases()), $choices);
    }
}
