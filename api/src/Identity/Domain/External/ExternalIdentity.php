<?php

declare(strict_types=1);

namespace App\Identity\Domain\External;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * "This Google account is that user": the provider's stable subject id, never the email
 * (which can change or be reassigned).
 */
class ExternalIdentity extends AggregateRoot
{
    private function __construct(
        private readonly ExternalIdentityId $id,
        private readonly UserId $userId,
        private readonly IdentityProvider $provider,
        private readonly string $subject,
        private readonly \DateTimeImmutable $linkedAt,
    ) {
    }

    public static function link(ExternalIdentityId $id, UserId $userId, IdentityProvider $provider, string $subject, \DateTimeImmutable $now): self
    {
        $identity = new self($id, $userId, $provider, $subject, $now);
        $identity->recordThat(new ExternalIdentityLinked($userId, $provider, $now));

        return $identity;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function provider(): IdentityProvider
    {
        return $this->provider;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function id(): ExternalIdentityId
    {
        return $this->id;
    }
}
