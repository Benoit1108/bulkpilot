<?php

declare(strict_types=1);

namespace App\Identity\Domain\External;

enum IdentityProvider: string
{
    case Google = 'google';
    /** Architecture only (ADR-0016): the adapter needs an Apple developer account. */
    case Apple = 'apple';
    /** Development and test only: never registered in production. */
    case Fake = 'fake';
}
