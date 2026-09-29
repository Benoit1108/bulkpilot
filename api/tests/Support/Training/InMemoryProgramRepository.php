<?php

declare(strict_types=1);

namespace App\Tests\Support\Training;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramRepository;

final class InMemoryProgramRepository implements ProgramRepository
{
    /** @var array<string, Program> */
    public array $programs = [];

    public function save(Program $program): void
    {
        $this->programs[$program->id()->value] = $program;
    }

    public function getVisibleTo(ProgramId $id, UserId $userId): Program
    {
        $program = $this->programs[$id->value] ?? null;
        if (null === $program || !$program->isVisibleTo($userId)) {
            throw ProgramNotFound::withId($id->value);
        }

        return $program;
    }

    public function activeOf(UserId $userId): ?Program
    {
        return array_find($this->programs, static fn (Program $program): bool => $program->isVisibleTo($userId) && $program->isActive());
    }

    public function countOf(UserId $userId): int
    {
        return \count(array_filter($this->programs, static fn (Program $program): bool => $program->isVisibleTo($userId)));
    }

    public function programsOf(UserId $userId): array
    {
        $programs = array_values(array_filter($this->programs, static fn (Program $program): bool => $program->isVisibleTo($userId)));
        usort($programs, static fn (Program $a, Program $b): int => [!$a->isActive(), mb_strtolower($a->name())] <=> [!$b->isActive(), mb_strtolower($b->name())]);

        return $programs;
    }
}
