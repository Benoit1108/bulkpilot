<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;

/**
 * The user's first program becomes their active one.
 */
final readonly class CreateProgram implements Command
{
    public function __construct(public ProgramId $programId, public ProgramVersionId $versionId, public UserId $userId, public ProgramDetails $details)
    {
    }
}
