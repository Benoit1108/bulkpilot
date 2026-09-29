<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;

/**
 * The program the user follows now; the one they followed before is no longer active.
 */
final readonly class ActivateProgram implements Command
{
    public function __construct(public ProgramId $programId, public UserId $userId)
    {
    }
}
