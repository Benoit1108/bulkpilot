<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

use App\Progression\Domain\Exception\InvalidMeasurement;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * A measure the user adds to the body parts (spec §54: « hanches », « avant-bras »…), in
 * centimetres. Archived rather than deleted: its values stay in the days that have them.
 */
class CustomMeasure extends AggregateRoot
{
    public const int MAX_NAME = 40;

    private function __construct(
        private readonly CustomMeasureId $id,
        private readonly UserId $ownerId,
        private string $name,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    public static function add(CustomMeasureId $id, UserId $owner, string $name, \DateTimeImmutable $now): self
    {
        return new self($id, $owner, self::named($name), $now);
    }

    public function rename(string $name): void
    {
        $this->name = self::named($name);
    }

    public function archive(\DateTimeImmutable $now): void
    {
        $this->archivedAt ??= $now;
    }

    public function key(): string
    {
        return $this->id->value;
    }

    public function id(): CustomMeasureId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    private static function named(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw InvalidMeasurement::because(\sprintf('a measure has a name of 1 to %d characters', self::MAX_NAME));
        }

        return $name;
    }
}
