<?php

declare(strict_types=1);

namespace App\Notification\Application\Settings;

use App\Notification\Domain\Notification\Category;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class ChangeNotificationSettings implements Command
{
    /**
     * @param list<Category> $enabled
     */
    public function __construct(public UserId $userId, public array $enabled, public ?string $quietFrom, public ?string $quietTo)
    {
    }
}
