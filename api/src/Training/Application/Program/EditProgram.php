<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;

/**
 * Renames the program and, when its days changed, records them as its next version.
 */
final readonly class EditProgram implements Command
{
    public function __construct(public ProgramId $programId, public ProgramVersionId $nextVersionId, public UserId $userId, public ProgramDetails $details)
    {
    }
}
