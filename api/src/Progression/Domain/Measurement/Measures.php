<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

use App\Progression\Domain\Exception\MeasureNotFound;

/**
 * The measures a user can write: the body parts, then their own not archived.
 */
final readonly class Measures
{
    /**
     * @param list<CustomMeasure> $custom
     */
    public function __construct(private array $custom)
    {
    }

    /**
     * @return list<string> the keys one can still write, body parts first
     */
    private function writable(): array
    {
        $custom = array_filter($this->custom, static fn (CustomMeasure $measure): bool => !$measure->isArchived());

        return [...array_map(static fn (BodyPart $part): string => $part->value, BodyPart::cases()), ...array_map(static fn (CustomMeasure $measure): string => $measure->key(), $custom)];
    }

    /**
     * @param array<string, float> $centimetres by key
     *
     * @return array<string, Length> in the order of the measures
     *
     * @throws MeasureNotFound for a key that is not one of the measures one can still take
     */
    public function lengths(array $centimetres): array
    {
        $writable = $this->writable();
        foreach (array_keys($centimetres) as $key) {
            if (!\in_array($key, $writable, true)) {
                throw MeasureNotFound::withKey($key);
            }
        }
        $lengths = [];
        foreach ($writable as $key) {
            if (\array_key_exists($key, $centimetres)) {
                $lengths[$key] = new Length($centimetres[$key]);
            }
        }

        return $lengths;
    }
}
