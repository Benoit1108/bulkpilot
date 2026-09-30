<?php

declare(strict_types=1);

namespace App\Identity\Domain\Dashboard;

use App\Identity\Domain\Exception\InvalidDashboardLayout;

/**
 * The order of the dashboard widgets and which are shown (spec §82): every widget exactly once.
 *
 * @phpstan-type StoredLayout list<array{key: string, visible: bool}>
 */
final readonly class DashboardLayout
{
    /**
     * @param list<WidgetPlacement> $placements
     */
    private function __construct(public array $placements)
    {
    }

    public static function default(): self
    {
        return new self(array_map(static fn (Widget $widget): WidgetPlacement => new WidgetPlacement($widget, true), Widget::cases()));
    }

    /**
     * A layout the user arranged: each known widget once, none missing.
     *
     * @param list<array{key: string, visible: bool}> $widgets
     */
    public static function arranged(array $widgets): self
    {
        $placements = [];
        foreach ($widgets as $widget) {
            $key = Widget::tryFrom($widget['key']) ?? throw InvalidDashboardLayout::because(\sprintf('"%s" is not a widget', $widget['key']));
            if (isset($placements[$key->value])) {
                throw InvalidDashboardLayout::because(\sprintf('"%s" is placed twice', $key->value));
            }
            $placements[$key->value] = new WidgetPlacement($key, $widget['visible']);
        }
        if (\count($placements) !== \count(Widget::cases())) {
            throw InvalidDashboardLayout::because('every widget is placed once');
        }

        return new self(array_values($placements));
    }

    /**
     * A saved layout, read back: a widget added since is appended (shown), one removed since is
     * dropped, so that an old layout never breaks the dashboard.
     *
     * @param StoredLayout $stored
     */
    public static function stored(array $stored): self
    {
        $placements = [];
        foreach ($stored as $widget) {
            $key = Widget::tryFrom($widget['key']);
            if (null !== $key && !isset($placements[$key->value])) {
                $placements[$key->value] = new WidgetPlacement($key, $widget['visible']);
            }
        }
        foreach (Widget::cases() as $widget) {
            $placements[$widget->value] ??= new WidgetPlacement($widget, true);
        }

        return new self(array_values($placements));
    }

    /**
     * @return StoredLayout
     */
    public function toArray(): array
    {
        return array_map(static fn (WidgetPlacement $placement): array => ['key' => $placement->widget->value, 'visible' => $placement->visible], $this->placements);
    }
}
