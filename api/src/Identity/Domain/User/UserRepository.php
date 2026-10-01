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

    /** Null once erased (or never there). */
    public function find(UserId $id): ?User;

    /**
     * The account, locked until the transaction ends (a sign-in cancelling its deletion waits,
     * then finds it erased): null once erased.
     */
    public function lockForErasure(UserId $id): ?User;

    /**
     * The accounts whose deletion delay is over.
     *
     * @return list<UserId>
     */
    public function dueForErasure(\DateTimeImmutable $now): array;
}
