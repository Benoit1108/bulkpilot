<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidIdentifier extends DomainException
{
    public static function fromValue(string $identifierClass, string $value): self
    {
        return new self(\sprintf('"%s" is not a valid %s.', $value, $identifierClass));
    }

    public function errorCode(): string
    {
        return 'shared.invalid_identifier';
    }
}
