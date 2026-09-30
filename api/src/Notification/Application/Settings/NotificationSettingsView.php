<?php

declare(strict_types=1);

namespace App\Notification\Application\Settings;

use App\Notification\Domain\Notification\Category;
use App\Notification\Domain\Settings\NotificationSettings;

final readonly class NotificationSettingsView
{
    /**
     * @param array<string, bool> $categories every category, on or off
     */
    public function __construct(public array $categories, public ?string $quietFrom, public ?string $quietTo)
    {
    }

    public static function of(NotificationSettings $settings): self
    {
        $categories = [];
        foreach (Category::cases() as $category) {
            $categories[$category->value] = $settings->isEnabled($category);
        }
        $quiet = $settings->quietHours();

        return new self($categories, $quiet?->from, $quiet?->to);
    }
}
