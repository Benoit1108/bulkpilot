<?php

declare(strict_types=1);

namespace App\Identity\Application\Account;

use App\Identity\Domain\User\Sex;

final readonly class AccountView
{
    public function __construct(
        public string $id,
        public string $email,
        public bool $emailVerified,
        public string $firstName,
        public ?\DateTimeImmutable $birthDate,
        public ?Sex $sex,
        public ?int $heightCm,
        public bool $twoFactorEnabled,
        public bool $hasPassword,
        public string $timeZone,
    ) {
    }
}
