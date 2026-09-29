<?php

declare(strict_types=1);

namespace App\Tests\Support\Training;

use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionRepository;

final class InMemoryProgramVersionRepository implements ProgramVersionRepository
{
    /** @var array<string, ProgramVersion> */
    public array $versions = [];

    public function save(ProgramVersion $version): void
    {
        $this->versions[$version->id()->value] = $version;
    }

    public function currentOf(Program $program): ProgramVersion
    {
        return $this->versions[$program->currentVersionId()->value] ?? throw ProgramNotFound::withId($program->id()->value);
    }

    public function numbered(Program $program, int $number): ProgramVersion
    {
        return array_find($this->versions, static fn (ProgramVersion $version): bool => $version->programId()->equals($program->id()) && $version->number() === $number)
            ?? throw ProgramNotFound::withId($program->id()->value);
    }

    public function currentOfEach(array $programs): array
    {
        $byProgram = [];
        foreach ($programs as $program) {
            $byProgram[$program->id()->value] = $this->currentOf($program);
        }

        return $byProgram;
    }

    public function historyOf(Program $program): array
    {
        $versions = array_values(array_filter($this->versions, static fn (ProgramVersion $version): bool => $version->programId()->equals($program->id())));
        usort($versions, static fn (ProgramVersion $a, ProgramVersion $b): int => $b->number() <=> $a->number());

        return array_map(static fn (ProgramVersion $version): array => ['number' => $version->number(), 'createdAt' => $version->createdAt()], $versions);
    }
}
