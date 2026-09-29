<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Training\Domain\Exception\ProgramNotFound;

/**
 * Versions are only read through their program, which the caller got for its user: no
 * version is ever read without its owner having been checked.
 */
interface ProgramVersionRepository
{
    public function save(ProgramVersion $version): void;

    public function currentOf(Program $program): ProgramVersion;

    /**
     * @throws ProgramNotFound for a number the program does not have
     */
    public function numbered(Program $program, int $number): ProgramVersion;

    /**
     * @param list<Program> $programs
     *
     * @return array<string, ProgramVersion> the current version of each, by program id
     */
    public function currentOfEach(array $programs): array;

    /**
     * @return list<array{number: int, createdAt: \DateTimeImmutable}> every version of the program, the latest first
     */
    public function historyOf(Program $program): array;
}
