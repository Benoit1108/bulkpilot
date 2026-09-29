<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Coach\Domain\Exception\RecommendationNotFound;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Coach\Domain\Recommendation\Status;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryRecommendationRepository implements RecommendationRepository
{
    /** @var array<string, Recommendation> */
    public array $recommendations = [];

    public function save(Recommendation $recommendation): void
    {
        $this->recommendations[$recommendation->id()->value] = $recommendation;
    }

    public function getOwnedBy(RecommendationId $id, UserId $userId): Recommendation
    {
        $recommendation = $this->recommendations[$id->value] ?? null;
        if (null === $recommendation || !$recommendation->ownerId()->equals($userId)) {
            throw RecommendationNotFound::because(\sprintf('No recommendation %s.', $id->value));
        }

        return $recommendation;
    }

    public function openOf(UserId $userId): array
    {
        return $this->latestFirst(array_filter($this->of($userId), static fn (Recommendation $recommendation): bool => self::stored($recommendation)->isOpen()));
    }

    public function decidedSince(UserId $userId, \DateTimeImmutable $since): array
    {
        return array_values(array_filter(
            $this->of($userId),
            static fn (Recommendation $recommendation): bool => \in_array(self::stored($recommendation), [Status::Accepted, Status::Rejected], true)
                && null !== $recommendation->decidedAt() && $recommendation->decidedAt() >= $since,
        ));
    }

    public function historyOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?string $beforeId = null): array
    {
        return \array_slice($this->latestFirst(array_filter(
            $this->of($userId),
            static fn (Recommendation $recommendation): bool => null === $before
                || $recommendation->createdAt() < $before
                || (null !== $beforeId && $recommendation->createdAt()->getTimestamp() === $before->getTimestamp() && $recommendation->id()->value < $beforeId),
        )), 0, $limit);
    }

    /**
     * @return list<Recommendation>
     */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter($this->recommendations, static fn (Recommendation $recommendation): bool => $kind === $recommendation->kind()->value));
    }

    /**
     * The status as stored: read long before any expiry, so that an open one past its time stays open.
     */
    public static function stored(Recommendation $recommendation): Status
    {
        return $recommendation->status(new \DateTimeImmutable('2000-01-01'));
    }

    /**
     * @return list<Recommendation>
     */
    private function of(UserId $userId): array
    {
        return array_values(array_filter($this->recommendations, static fn (Recommendation $recommendation): bool => $recommendation->ownerId()->equals($userId)));
    }

    /**
     * @param array<array-key, Recommendation> $recommendations
     *
     * @return list<Recommendation>
     */
    private function latestFirst(array $recommendations): array
    {
        $recommendations = array_values($recommendations);
        // As the database: the latest first, then by id (descending).
        usort($recommendations, static fn (Recommendation $a, Recommendation $b): int => [$b->createdAt(), $b->id()->value] <=> [$a->createdAt(), $a->id()->value]);

        return $recommendations;
    }
}
