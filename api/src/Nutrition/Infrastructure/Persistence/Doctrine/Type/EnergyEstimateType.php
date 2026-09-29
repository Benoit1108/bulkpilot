<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Target\EnergyEstimate;
use App\Nutrition\Domain\Target\EnergyInputs;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\Sex;
use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * The calculation a target came from, kept as a JSON snapshot so it can be explained later.
 */
final class EnergyEstimateType extends JsonType
{
    public const string NAME = 'energy_estimate';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EnergyEstimate
    {
        $data = parent::convertToPHPValue($value, $platform);
        if (null === $data) {
            return null;
        }

        try {
            /** @var array{inputs: array{sex: string, age: int, heightCm: int, weightKg: int|float, lifestyle: string, trainingSessionsPerWeek: int, goal: string, weeklyWeightChangeKg: int|float}, basalMetabolicRate: int|float, activityLevel: int|float, trainingAllowance: int|float, totalExpenditure: int|float, dailyAdjustment: int|float, targets: array{energyKcal: int, proteinGrams: int, carbohydrateGrams: int, fatGrams: int}} $data */
            $inputs = $data['inputs'];
            $targets = $data['targets'];

            return new EnergyEstimate(
                Reconstitute::of(EnergyInputs::class, [
                    'sex' => Sex::from($inputs['sex']),
                    'age' => $inputs['age'],
                    'heightCm' => $inputs['heightCm'],
                    'weightKg' => (float) $inputs['weightKg'],
                    'lifestyle' => Lifestyle::from($inputs['lifestyle']),
                    'trainingSessionsPerWeek' => $inputs['trainingSessionsPerWeek'],
                    'goal' => Goal::from($inputs['goal']),
                    'weeklyWeightChangeKg' => (float) $inputs['weeklyWeightChangeKg'],
                ]),
                (float) $data['basalMetabolicRate'],
                (float) $data['activityLevel'],
                (float) $data['trainingAllowance'],
                (float) $data['totalExpenditure'],
                (float) $data['dailyAdjustment'],
                Reconstitute::of(MacroTargets::class, [
                    'energyKcal' => $targets['energyKcal'],
                    'proteinGrams' => $targets['proteinGrams'],
                    'carbohydrateGrams' => $targets['carbohydrateGrams'],
                    'fatGrams' => $targets['fatGrams'],
                ]),
            );
        } catch (\Throwable $e) {
            throw ValueNotConvertible::new($value, self::NAME, null, $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof EnergyEstimate) {
            throw InvalidType::new($value, self::class, ['null', EnergyEstimate::class]);
        }

        $inputs = $value->inputs;

        return parent::convertToDatabaseValue([
            'inputs' => [
                'sex' => $inputs->sex->value,
                'age' => $inputs->age,
                'heightCm' => $inputs->heightCm,
                'weightKg' => $inputs->weightKg,
                'lifestyle' => $inputs->lifestyle->value,
                'trainingSessionsPerWeek' => $inputs->trainingSessionsPerWeek,
                'goal' => $inputs->goal->value,
                'weeklyWeightChangeKg' => $inputs->weeklyWeightChangeKg,
            ],
            'basalMetabolicRate' => $value->basalMetabolicRate,
            'activityLevel' => $value->activityLevel,
            'trainingAllowance' => $value->trainingAllowance,
            'totalExpenditure' => $value->totalExpenditure,
            'dailyAdjustment' => $value->dailyAdjustment,
            'targets' => [
                'energyKcal' => $value->targets->energyKcal,
                'proteinGrams' => $value->targets->proteinGrams,
                'carbohydrateGrams' => $value->targets->carbohydrateGrams,
                'fatGrams' => $value->targets->fatGrams,
            ],
        ], $platform);
    }
}
