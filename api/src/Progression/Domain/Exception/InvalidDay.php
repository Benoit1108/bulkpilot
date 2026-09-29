<?php

declare(strict_types=1);

namespace App\Progression\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidDay extends DomainException
{
    public static function notADay(): self
    {
        return new self('a day is written YYYY-MM-DD');
    }

    public function errorCode(): string
    {
        return 'progression.invalid_day';
    }
}
