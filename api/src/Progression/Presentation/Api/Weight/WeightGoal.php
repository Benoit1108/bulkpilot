<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Progression\Domain\Goal\GoalProgress;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The weight goal followed, its milestones, and where the user stands on it (spec §52–53).
 */
#[ApiResource(
    shortName: 'WeightGoal',
    operations: [
        new Get(
            uriTemplate: '/progression/weight-goal',
            description: 'The goal followed and the progress on it; 404 without one.',
            provider: WeightGoalProvider::class,
            parameters: ['today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD).', schema: ['type' => 'string', 'format' => 'date'])],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
        new Put(
            uriTemplate: '/progression/weight-goal',
            description: 'Sets the goal to follow, from the latest weigh-in; the previous goal ends (it is kept).',
            input: WeightGoalInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: WeightGoalProcessor::class,
        ),
        new Delete(
            uriTemplate: '/progression/weight-goal',
            description: 'Stops following the goal (it is kept); without one, nothing changes.',
            read: false,
            openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]),
            processor: WeightGoalProcessor::class,
        ),
    ],
)]
final readonly class WeightGoal
{
    /**
     * @param list<GoalStepOutput> $steps
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $startDay,
        #[ApiProperty(required: true)]
        public float $startWeightKg,
        #[ApiProperty(required: true)]
        public float $targetKg,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['gaining', 'losing']])]
        public string $direction,
        #[ApiProperty(description: 'The milestones then the target, in the order they come.', required: true)]
        public array $steps,
        #[ApiProperty(description: 'Share of the way from the start to the target, 0 to 100.', required: true)]
        public int $percent,
        #[ApiProperty(description: 'Change since the last step reached (or the start).', required: true)]
        public float $sinceLastStepKg,
        #[ApiProperty(description: 'The next step to reach, what is left to it and the estimate; null once the target is reached.', required: true, readableLink: true, genId: false)]
        public ?NextStepOutput $next,
        #[ApiProperty(required: true)]
        public float $toTargetKg,
        #[ApiProperty(required: true)]
        public EstimateOutput $toTarget,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $setAt,
    ) {
    }

    public static function of(GoalProgress $progress): self
    {
        $goal = $progress->goal;

        return new self(
            $goal->id()->value,
            $goal->startDay()->format('Y-m-d'),
            $goal->startKg(),
            $goal->targetKg(),
            $goal->direction()->value,
            array_map(GoalStepOutput::of(...), $progress->steps),
            $progress->percent,
            $progress->sinceLastStepKg,
            null === $progress->next ? null : NextStepOutput::of($progress->next),
            $progress->toTargetKg,
            EstimateOutput::of($progress->toTarget),
            $goal->setAt(),
        );
    }
}
