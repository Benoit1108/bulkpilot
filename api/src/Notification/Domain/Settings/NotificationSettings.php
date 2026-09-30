<?php

declare(strict_types=1);

namespace App\Notification\Domain\Settings;

use App\Notification\Domain\Notification\Category;
use App\Shared\Domain\Identifier\UserId;

/**
 * What the user wants to be told: the categories turned off (a new category is on) and the quiet
 * hours, if any. Only the user changes it (when the checks ran is kept apart, see CheckLog).
 */
class NotificationSettings
{
    /** Optimistic lock (Doctrine): two devices saving at once do not overwrite each other unseen. */
    private int $version = 1;

    /**
     * @param list<string> $disabled category values
     */
    private function __construct(
        private readonly UserId $ownerId,
        private array $disabled,
        private ?string $quietFrom,
        private ?string $quietTo,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function defaults(UserId $owner, \DateTimeImmutable $now): self
    {
        $quiet = QuietHours::default();

        return new self($owner, [], $quiet->from, $quiet->to, $now);
    }

    /**
     * @param list<Category> $enabled
     */
    public function change(array $enabled, ?QuietHours $quiet, \DateTimeImmutable $now): void
    {
        $this->disabled = array_values(array_map(
            static fn (Category $category): string => $category->value,
            array_filter(Category::cases(), static fn (Category $category): bool => !\in_array($category, $enabled, true)),
        ));
        $this->quietFrom = $quiet?->from;
        $this->quietTo = $quiet?->to;
        $this->updatedAt = $now;
    }

    public function isEnabled(Category $category): bool
    {
        return !\in_array($category->value, $this->disabled, true);
    }

    /** Nothing can be told: every category is off. */
    public function allOff(): bool
    {
        return [] === array_filter(Category::cases(), $this->isEnabled(...));
    }

    /** Whether nothing is told at that local time (spec §76). */
    public function isQuietAt(\DateTimeImmutable $localNow): bool
    {
        return $this->quietHours()?->covers($localNow) ?? false;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    /** Null when the user wants none. */
    public function quietHours(): ?QuietHours
    {
        return null === $this->quietFrom || null === $this->quietTo ? null : new QuietHours($this->quietFrom, $this->quietTo);
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
