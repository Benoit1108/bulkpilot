<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\Exception\UserNotFound;
use App\Shared\Domain\Identifier\UserId;

interface UserRepository
{
    public function save(User $user): void;

    /**
     * @throws UserNotFound
     */
    public function get(UserId $id): User;

    public function findByEmail(EmailAddress $email): ?User;
}
