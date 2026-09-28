<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface RegistrationPolicy
{
    public function isOpen(): bool;
}
