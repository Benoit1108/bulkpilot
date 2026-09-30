<?php

declare(strict_types=1);

namespace App\Identity\Domain\Dashboard;

final readonly class WidgetPlacement
{
    public function __construct(public Widget $widget, public bool $visible)
    {
    }
}
