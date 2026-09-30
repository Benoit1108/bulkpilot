<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use Symfony\Component\Validator\Constraints as Assert;

final class DashboardLayoutInput
{
    /** @var list<WidgetInput> every widget once, in the order to show them */
    #[Assert\Count(max: 50)]
    #[Assert\Valid]
    public array $widgets = [];

    /**
     * @return list<array{key: string, visible: bool}>
     */
    public function widgets(): array
    {
        return array_map(static fn (WidgetInput $widget): array => ['key' => $widget->key, 'visible' => true === $widget->visible], $this->widgets);
    }
}
