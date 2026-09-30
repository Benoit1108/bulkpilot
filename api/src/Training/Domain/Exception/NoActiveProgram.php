<?php

declare(strict_types=1);

namespace App\Training\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class NoActiveProgram extends NotFound
{
    public static function create(): self
    {
        return new self('No active program.');
    }

    public function errorCode(): string
    {
        return 'training.no_active_program';
    }
}
