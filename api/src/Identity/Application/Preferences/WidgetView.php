<?php

declare(strict_types=1);

namespace App\Identity\Application\Preferences;

final readonly class WidgetView
{
    public function __construct(public string $key, public bool $visible)
    {
    }
}
