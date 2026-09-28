<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Application\Security\CurrentUser;
use App\Shared\Application\Security\NotAuthenticated;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityCurrentUser implements CurrentUser
{
    public function __construct(private Security $security)
    {
    }

    public function id(): UserId
    {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new NotAuthenticated('No authenticated user for this request.');
        }

        return new UserId($user->userId);
    }
}
