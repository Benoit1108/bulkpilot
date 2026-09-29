<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use App\Training\Application\Program\ProgramDetails;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramVersion;
use Symfony\Component\Validator\Constraints as Assert;

final class ProgramInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: Program::MAX_NAME_LENGTH, normalizer: 'trim')]
    public string $name = '';

    /** @var list<DayInput> */
    #[Assert\Count(min: 1, max: ProgramVersion::MAX_DAYS)]
    #[Assert\Valid]
    public array $days = [];

    public function details(): ProgramDetails
    {
        return new ProgramDetails($this->name, array_map(static fn (DayInput $day): array => $day->details(), $this->days));
    }
}
