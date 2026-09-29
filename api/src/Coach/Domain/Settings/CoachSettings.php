<?php

declare(strict_types=1);

namespace App\Coach\Domain\Settings;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Exception\InvalidCoachSettings;
use App\Shared\Domain\Identifier\UserId;

/**
 * The user's Coach: how it speaks, the pace it judges the weight against when the nutrition
 * target does not tell it, the last day it analysed and the adherence found that day.
 *
 * @phpstan-import-type AdherenceArray from Adherence
 */
class CoachSettings
{
    /** The same bounds as a nutrition estimate: at most 1 kg a week either way. */
    public const float MAX_PACE_KG = 1.0;
    /** Direct by default: the Coach is actionable (spec §130). */
    public const Personality DEFAULT_PERSONALITY = Personality::Direct;

    /** Optimistic lock (Doctrine): of two analyses at the same time, only the first runs. */
    private int $version = 1;
    /** @var AdherenceArray|null */
    private ?array $adherence = null;

    private function __construct(
        private readonly UserId $ownerId,
        private Personality $personality,
        private ?float $paceKgPerWeek,
        private ?\DateTimeImmutable $analysedOn,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function defaults(UserId $owner, \DateTimeImmutable $now): self
    {
        return new self($owner, self::DEFAULT_PERSONALITY, null, null, $now);
    }

    public function change(Personality $personality, ?float $paceKgPerWeek, \DateTimeImmutable $now): void
    {
        if (null !== $paceKgPerWeek && (abs($paceKgPerWeek) > self::MAX_PACE_KG || round($paceKgPerWeek, 2) !== $paceKgPerWeek)) {
            throw InvalidCoachSettings::because('the pace is between -1 and 1 kg a week, to the hundredth');
        }
        $this->personality = $personality;
        $this->paceKgPerWeek = $paceKgPerWeek;
        $this->updatedAt = $now;
    }

    /**
     * Whether the analysis already ran for that day or a later one: it runs again only when
     * asked, and never goes back to an earlier day (the device's today may be a day off).
     */
    public function hasAnalysed(\DateTimeImmutable $day): bool
    {
        return null !== $this->analysedOn && $this->analysedOn >= $day->setTime(0, 0);
    }

    public function markAnalysed(\DateTimeImmutable $day, Adherence $adherence): void
    {
        $this->analysedOn = $day->setTime(0, 0);
        $this->adherence = $adherence->toArray();
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function personality(): Personality
    {
        return $this->personality;
    }

    public function paceKgPerWeek(): ?float
    {
        return $this->paceKgPerWeek;
    }

    public function lastAnalysedOn(): ?\DateTimeImmutable
    {
        return $this->analysedOn;
    }

    /** The adherence of the 7 days before the last analysis; null before the first one. */
    public function adherence(): ?Adherence
    {
        return null === $this->adherence ? null : Adherence::fromArray($this->adherence);
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
