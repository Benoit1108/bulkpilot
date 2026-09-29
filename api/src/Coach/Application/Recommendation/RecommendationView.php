<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\Status;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;

/**
 * @phpstan-import-type Figures from Finding
 */
final readonly class RecommendationView
{
    /**
     * @param list<string> $why
     * @param Figures      $figures
     */
    public function __construct(
        public string $id,
        public Kind $kind,
        public Level $level,
        public string $title,
        public string $body,
        public array $why,
        public ?CalorieProposal $proposal,
        public array $figures,
        /** As the user sees it now: an open one past its time is expired. */
        public Status $status,
        public string $provider,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $postponedUntil,
        public ?\DateTimeImmutable $decidedAt,
    ) {
    }

    public static function of(Recommendation $recommendation, \DateTimeImmutable $now): self
    {
        $message = $recommendation->message();

        return new self(
            $recommendation->id()->value,
            $recommendation->kind(),
            $recommendation->level(),
            $message->title,
            $message->body,
            $message->why,
            $recommendation->proposal(),
            $recommendation->figures(),
            $recommendation->status($now),
            $message->provider,
            $recommendation->createdAt(),
            $recommendation->expiresAt(),
            $recommendation->postponedUntil(),
            $recommendation->decidedAt(),
        );
    }
}
