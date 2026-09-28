<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Identity\Domain\User\Sex;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class UpdateProfile implements Command
{
    public function __construct(
        public UserId $userId,
        public string $firstName,
        public ?\DateTimeImmutable $birthDate,
        public ?Sex $sex,
        public ?int $heightCm,
    ) {
    }
}
