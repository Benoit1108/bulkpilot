<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\Notification;

final readonly class NotificationView
{
    public function __construct(
        public string $id,
        public string $category,
        public string $title,
        public string $body,
        public ?string $link,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readAt,
    ) {
    }

    public static function of(Notification $notification): self
    {
        return new self(
            $notification->id()->value,
            $notification->category()->value,
            $notification->title(),
            $notification->body(),
            $notification->link(),
            $notification->createdAt(),
            $notification->readAt(),
        );
    }
}
