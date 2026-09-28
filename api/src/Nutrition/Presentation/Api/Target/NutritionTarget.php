<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Nutrition\Application\Target\TargetView;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Daily targets from a day on. Never edited: each change is a new entry of the history.
 */
#[ApiResource(
    shortName: 'NutritionTarget',
    operations: [
        new GetCollection(
            uriTemplate: '/nutrition/targets',
            description: 'Target history, most recent first (the first one effective on or before today is in force).',
            paginationEnabled: false,
            provider: NutritionTargetProvider::class,
        ),
        new Post(
            uriTemplate: '/nutrition/targets',
            description: 'Sets new daily targets from today (or tomorrow). With the estimate they came from, they are recorded as "estimate" when unchanged, "manual" otherwise.',
            input: TargetInput::class,
            processor: NutritionTargetProcessor::class,
        ),
    ],
)]
final readonly class NutritionTarget
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        #[ApiProperty(required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public \DateTimeImmutable $effectiveFrom,
        #[ApiProperty(required: true)]
        public int $energyKcal,
        #[ApiProperty(required: true)]
        public int $proteinGrams,
        #[ApiProperty(required: true)]
        public int $carbohydrateGrams,
        #[ApiProperty(required: true)]
        public int $fatGrams,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['estimate', 'manual', 'coach']])]
        public string $source,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public ?EnergyEstimate $estimate,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $setAt,
    ) {
    }

    public static function fromView(TargetView $view): self
    {
        $targets = $view->targets;

        return new self(
            $view->id,
            $view->effectiveFrom,
            $targets->energyKcal,
            $targets->proteinGrams,
            $targets->carbohydrateGrams,
            $targets->fatGrams,
            $view->source->value,
            null === $view->estimate ? null : EnergyEstimate::of($view->estimate),
            $view->setAt,
        );
    }
}
