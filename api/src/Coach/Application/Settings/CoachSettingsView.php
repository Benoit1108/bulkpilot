<?php

declare(strict_types=1);

namespace App\Coach\Application\Settings;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\Personality;

final readonly class CoachSettingsView
{
    public function __construct(
        public Personality $personality,
        public ?float $paceKgPerWeek,
        public ?\DateTimeImmutable $lastAnalysedOn,
        /** The adherence of the 7 days before the last analysis; null before the first one. */
        public ?Adherence $adherence,
    ) {
    }

    public static function of(CoachSettings $settings): self
    {
        return new self($settings->personality(), $settings->paceKgPerWeek(), $settings->lastAnalysedOn(), $settings->adherence());
    }
}
