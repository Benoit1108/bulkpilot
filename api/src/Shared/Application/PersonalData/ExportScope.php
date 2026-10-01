<?php

declare(strict_types=1);

namespace App\Shared\Application\PersonalData;

/**
 * What an export covers: a period (the user's days, both included; null for everything) and
 * domains (empty for all of them), in the user's time zone: a workout started at 00:30 in Paris
 * belongs to that Paris day.
 */
final readonly class ExportScope
{
    /**
     * @param list<string> $domains
     */
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public array $domains = [],
        public string $timeZone = 'UTC',
    ) {
    }

    public function covers(string $domain): bool
    {
        return [] === $this->domains || \in_array($domain, $this->domains, true);
    }

    /** Whether a day (`YYYY-MM-DD`) or a moment (taken in the user's zone) falls in the period. */
    public function includes(\DateTimeImmutable|string $day): bool
    {
        $day = \is_string($day) ? $day : $day->setTimezone(new \DateTimeZone($this->timeZone))->format('Y-m-d');

        return (null === $this->from || $day >= $this->from->format('Y-m-d')) && (null === $this->to || $day <= $this->to->format('Y-m-d'));
    }
}
