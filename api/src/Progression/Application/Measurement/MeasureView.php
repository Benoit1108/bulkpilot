<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\BodyPart;
use App\Progression\Domain\Measurement\CustomMeasure;

/**
 * A measure: a body part (named by the app) or one of the user's own (named by them).
 */
final readonly class MeasureView
{
    public function __construct(public string $key, public ?string $name, public bool $archived)
    {
    }

    public static function part(BodyPart $part): self
    {
        return new self($part->value, null, false);
    }

    public static function custom(CustomMeasure $measure): self
    {
        return new self($measure->key(), $measure->name(), $measure->isArchived());
    }
}
