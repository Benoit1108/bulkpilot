<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;

/**
 * @implements Query<ProgramView>
 */
final readonly class GetProgram implements Query
{
    public function __construct(public UserId $userId, public ProgramId $programId, public ?int $versionNumber = null)
    {
    }
}
