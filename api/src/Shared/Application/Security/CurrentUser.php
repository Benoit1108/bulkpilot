<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

use App\Shared\Domain\Identifier\UserId;

/**
 * The authenticated user of the current request. Use cases receive the UserId explicitly;
 * only the presentation layer resolves it through this port.
 */
interface CurrentUser
{
    /**
     * @throws NotAuthenticated when the request is anonymous
     */
    public function id(): UserId;
}
