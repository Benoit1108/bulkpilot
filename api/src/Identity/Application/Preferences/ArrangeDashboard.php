<?php

declare(strict_types=1);

namespace App\Identity\Application\Preferences;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class ArrangeDashboard implements Command
{
    /**
     * @param list<array{key: string, visible: bool}> $widgets in the order to show them
     */
    public function __construct(public UserId $userId, public array $widgets)
    {
    }
}
