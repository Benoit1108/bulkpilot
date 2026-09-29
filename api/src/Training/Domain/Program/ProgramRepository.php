<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ProgramNotFound;

interface ProgramRepository
{
    public function save(Program $program): void;

    /**
     * @throws ProgramNotFound when it does not exist, is archived or is someone else's
     */
    public function getVisibleTo(ProgramId $id, UserId $userId): Program;

    public function activeOf(UserId $userId): ?Program;

    /**
     * The user's programs, archived ones aside.
     */
    public function countOf(UserId $userId): int;

    /**
     * @return list<Program> the user's programs, the active one first, then by name
     */
    public function programsOf(UserId $userId): array;
}
