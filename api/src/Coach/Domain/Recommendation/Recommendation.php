<?php

declare(strict_types=1);

namespace App\Coach\Domain\Recommendation;

use App\Coach\Domain\Exception\RecommendationClosed;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * A recommendation of the Coach (spec §68–70): what it found, the figures that led to it, how it
 * was phrased and what the user decided. Nothing changes until the user accepts (spec §132).
 *
 * @phpstan-import-type Figures from Finding
 */
class Recommendation extends AggregateRoot
{
    /** Offered this long; after it, the next analysis looks at fresh data. */
    public const int OPEN_DAYS = 7;
    /** "Plus tard" puts it aside this long. */
    public const int POSTPONE_DAYS = 2;

    /** Optimistic lock (Doctrine): of two decisions, or a decision and an analysis, only the first wins. */
    private int $version = 1;

    /**
     * @param Figures      $figures
     * @param list<string> $why
     */
    private function __construct(
        private readonly RecommendationId $id,
        private readonly UserId $ownerId,
        private readonly Kind $kind,
        private readonly Level $level,
        private readonly array $figures,
        private readonly ?int $proposalFromKcal,
        private readonly ?int $proposalByKcal,
        private readonly string $title,
        private readonly string $body,
        private readonly array $why,
        private readonly string $action,
        private readonly string $provider,
        private Status $status,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $postponedUntil,
        private ?\DateTimeImmutable $decidedAt,
    ) {
    }

    public static function make(RecommendationId $id, UserId $owner, Finding $finding, CoachMessage $message, \DateTimeImmutable $now): self
    {
        $recommendation = new self(
            $id,
            $owner,
            $finding->kind,
            $finding->level,
            $finding->figures,
            $finding->proposal?->fromKcal,
            $finding->proposal?->byKcal,
            $message->title,
            $message->body,
            $message->why,
            $message->action,
            $message->provider,
            Status::Pending,
            $now,
            $now->modify(\sprintf('+%d days', self::OPEN_DAYS)),
            null,
            null,
        );
        $recommendation->recordThat(new RecommendationMade($id, $owner, $finding->kind, $message->provider, $now));

        return $recommendation;
    }

    /** Still offered: open and not too old. */
    public function isOpen(\DateTimeImmutable $now): bool
    {
        return $this->status->isOpen() && $now < $this->expiresAt;
    }

    /** Offered and not put aside. */
    public function isShown(\DateTimeImmutable $now): bool
    {
        return $this->isOpen($now) && (null === $this->postponedUntil || $this->postponedUntil <= $now);
    }

    /** What the finding says is still what this recommendation says. */
    public function matches(Finding $finding): bool
    {
        return $this->kind === $finding->kind && (null === $finding->proposal ? null === $this->proposal() : $finding->proposal->equals($this->proposal()));
    }

    public function accept(\DateTimeImmutable $now): void
    {
        $this->decide(Status::Accepted, $now);
    }

    public function reject(\DateTimeImmutable $now): void
    {
        $this->decide(Status::Rejected, $now);
    }

    public function postpone(\DateTimeImmutable $now): void
    {
        $this->decide(Status::Postponed, $now);
        $this->postponedUntil = $now->modify(\sprintf('+%d days', self::POSTPONE_DAYS));
    }

    /** No longer offered; an already closed one stays as it is. */
    public function expire(\DateTimeImmutable $now): void
    {
        if ($this->status->isOpen()) {
            $this->status = Status::Expired;
            $this->decidedAt = $now;
        }
    }

    public function id(): RecommendationId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function kind(): Kind
    {
        return $this->kind;
    }

    public function level(): Level
    {
        return $this->level;
    }

    /**
     * @return Figures
     */
    public function figures(): array
    {
        return $this->figures;
    }

    public function proposal(): ?CalorieProposal
    {
        return null === $this->proposalFromKcal || null === $this->proposalByKcal ? null : new CalorieProposal($this->proposalFromKcal, $this->proposalByKcal);
    }

    public function message(): CoachMessage
    {
        return new CoachMessage($this->title, $this->body, $this->why, $this->action, $this->provider);
    }

    /** The status as the user sees it: an open one past its time is expired. */
    public function status(\DateTimeImmutable $now): Status
    {
        return $this->status->isOpen() && $now >= $this->expiresAt ? Status::Expired : $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function postponedUntil(): ?\DateTimeImmutable
    {
        return $this->postponedUntil;
    }

    public function decidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    private function decide(Status $status, \DateTimeImmutable $now): void
    {
        if (!$this->isOpen($now)) {
            throw RecommendationClosed::because('this recommendation is no longer offered');
        }
        $this->status = $status;
        $this->decidedAt = $now;
        $this->recordThat(new RecommendationDecided($this->id, $this->ownerId, $this->kind, $status, $now));
    }
}
