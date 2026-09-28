<?php

declare(strict_types=1);

namespace App\Identity\Domain\Session;

use App\Shared\Domain\Identifier\UserId;

/**
 * A signed-in device. The HTTP session references it; revoking it signs the device out
 * on its next request.
 */
class UserSession
{
    /** Activity is recorded at most this often, to avoid a write on every request. */
    public const int ACTIVITY_RESOLUTION_SECONDS = 300;
    /** Same as the HTTP session idle lifetime (framework.session.gc_maxlifetime): an idle device is signed out. */
    public const int IDLE_LIFETIME_SECONDS = 30 * 24 * 3600;

    private \DateTimeImmutable $lastSeenAt;
    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        private readonly UserSessionId $id,
        private readonly UserId $userId,
        private readonly string $deviceLabel,
        private readonly \DateTimeImmutable $openedAt,
    ) {
        $this->lastSeenAt = $openedAt;
    }

    public static function open(UserSessionId $id, UserId $userId, string $deviceLabel, \DateTimeImmutable $now): self
    {
        $label = mb_substr(trim($deviceLabel), 0, 100);

        return new self($id, $userId, '' === $label ? 'Appareil inconnu' : $label, $now);
    }

    /**
     * @return bool whether the activity changed and must be persisted
     */
    public function recordActivity(\DateTimeImmutable $now): bool
    {
        if ($now->getTimestamp() - $this->lastSeenAt->getTimestamp() < self::ACTIVITY_RESOLUTION_SECONDS) {
            return false;
        }

        $this->lastSeenAt = $now;

        return true;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $now->getTimestamp() - $this->lastSeenAt->getTimestamp() < self::IDLE_LIFETIME_SECONDS;
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function id(): UserSessionId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function deviceLabel(): string
    {
        return $this->deviceLabel;
    }

    public function openedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }
}
