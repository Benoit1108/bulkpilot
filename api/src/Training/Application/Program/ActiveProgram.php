<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersionRepository;

/**
 * What the active program plans, as the other contexts read it (the Coach, the notifications).
 */
final readonly class ActiveProgram
{
    public function __construct(private ProgramRepository $programs, private ProgramVersionRepository $versions)
    {
    }

    /** The days of its current version: the sessions planned a week; null without an active program. */
    public function plannedPerWeek(UserId $userId): ?int
    {
        $program = $this->programs->activeOf($userId);

        return null === $program ? null : \count($this->versions->currentOf($program)->days());
    }
}
