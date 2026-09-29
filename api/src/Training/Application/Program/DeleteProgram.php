<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;

/**
 * Archives the program: past workouts keep the version they followed.
 */
final readonly class DeleteProgram implements Command
{
    public function __construct(public ProgramId $programId, public UserId $userId)
    {
    }
}
