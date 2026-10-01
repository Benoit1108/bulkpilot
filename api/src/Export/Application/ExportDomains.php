<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\Application\PersonalData\PersonalData;

/**
 * The personal data of every context (ADR-0030), and those a user can pick for an export: the ones
 * with a title (Media keeps the files the others export; Export keeps the exports). A context added
 * later is exported too, after these.
 */
final readonly class ExportDomains
{
    /** The order the known domains come in. */
    private const array ORDER = ['identity', 'nutrition', 'training', 'progression', 'coach', 'notification', 'activity'];

    /** @var list<PersonalData> */
    private array $sources;

    /**
     * @param iterable<PersonalData> $sources
     */
    public function __construct(iterable $sources)
    {
        $this->sources = array_values([...$sources]);
    }

    /**
     * @return array<string, string> the exportable domains and their titles, in order
     */
    public function titles(): array
    {
        $titles = [];
        foreach ($this->exportable() as $source) {
            $titles[$source->domain()] = (string) $source->title();
        }

        return $titles;
    }

    /**
     * @return list<PersonalData> the exportable sources, in order
     */
    public function exportable(): array
    {
        $sources = array_values(array_filter($this->sources, static fn (PersonalData $source): bool => null !== $source->title()));
        $rank = static fn (PersonalData $source): int => false === ($position = array_search($source->domain(), self::ORDER, true)) ? \count(self::ORDER) : $position;
        usort($sources, static fn (PersonalData $a, PersonalData $b): int => [$rank($a), $a->domain()] <=> [$rank($b), $b->domain()]);

        return $sources;
    }
}
