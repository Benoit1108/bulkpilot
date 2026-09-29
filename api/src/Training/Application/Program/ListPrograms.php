<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<ProgramView>>
 */
final readonly class ListPrograms implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
