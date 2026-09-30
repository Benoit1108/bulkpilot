<?php

declare(strict_types=1);

namespace App\Notification\Domain\Notification;

use App\Notification\Domain\Check\Draft;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * Something worth telling the user (spec §75), shown in the app until read.
 */
class Notification extends AggregateRoot
{
    private function __construct(
        private readonly NotificationId $id,
        private readonly UserId $ownerId,
        private readonly Category $category,
        private readonly string $dedupKey,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $link,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $readAt,
    ) {
    }

    public static function of(NotificationId $id, UserId $owner, Draft $draft, \DateTimeImmutable $now): self
    {
        $notification = new self($id, $owner, $draft->category, $draft->key, $draft->title, $draft->body, $draft->link, $now, null);
        $notification->recordThat(new NotificationCreated($id, $owner, $now));

        return $notification;
    }

    /** Reading it again keeps the first time. */
    public function markRead(\DateTimeImmutable $now): void
    {
        $this->readAt ??= $now;
    }

    public function id(): NotificationId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function dedupKey(): string
    {
        return $this->dedupKey;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function link(): ?string
    {
        return $this->link;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function readAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
}
