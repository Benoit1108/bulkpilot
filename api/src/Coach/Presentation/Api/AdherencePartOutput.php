<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Coach\Domain\Analysis\AdherenceComponent;

final readonly class AdherencePartOutput
{
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['calories', 'protein', 'training', 'weigh_ins']])]
        public string $part,
        #[ApiProperty(required: true)]
        public int $done,
        #[ApiProperty(required: true)]
        public int $expected,
        #[ApiProperty(description: '0 to 100.', required: true)]
        public int $score,
    ) {
    }

    public static function of(AdherenceComponent $component): self
    {
        return new self($component->part->value, $component->done, $component->expected, $component->score());
    }
}
