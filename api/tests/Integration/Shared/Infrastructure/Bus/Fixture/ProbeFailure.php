<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Domain\Exception\DomainException;

final class ProbeFailure extends DomainException
{
    public function errorCode(): string
    {
        return 'test.probe_failure';
    }
}
