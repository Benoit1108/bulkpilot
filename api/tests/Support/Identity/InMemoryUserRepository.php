<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    public array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->value] = $user;
    }

    public function get(UserId $id): User
    {
        return $this->users[$id->value] ?? throw UserNotFound::withId($id);
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function find(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    public function lockForErasure(UserId $id): ?User
    {
        return $this->find($id);
    }

    public function dueForErasure(\DateTimeImmutable $now): array
    {
        $due = array_filter($this->users, static fn (User $user): bool => $user->isDueForErasure($now));

        return array_values(array_map(static fn (User $user): UserId => $user->id(), $due));
    }
}
