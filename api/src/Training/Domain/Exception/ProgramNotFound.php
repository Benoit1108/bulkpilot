<?php

declare(strict_types=1);

namespace App\Training\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class ProgramNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Program "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'training.program_not_found';
    }
}
