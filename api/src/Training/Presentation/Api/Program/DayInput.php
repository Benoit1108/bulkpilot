<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use App\Training\Application\Program\ProgramDetails;
use App\Training\Domain\Program\TrainingDay;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-import-type Day from ProgramDetails
 */
final class DayInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: TrainingDay::MAX_NAME_LENGTH, normalizer: 'trim')]
    public string $name = '';

    /** @var list<BlockInput> */
    #[Assert\Count(min: 1, max: TrainingDay::MAX_BLOCKS)]
    #[Assert\Valid]
    public array $blocks = [];

    /**
     * @return Day
     */
    public function details(): array
    {
        return ['name' => $this->name, 'blocks' => array_map(static fn (BlockInput $block): array => $block->details(), $this->blocks)];
    }
}
