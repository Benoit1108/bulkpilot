<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\ApiProperty;

final readonly class ProgramVersionSummary
{
    public function __construct(
        #[ApiProperty(required: true)]
        public int $number,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
