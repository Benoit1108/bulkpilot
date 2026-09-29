<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Rest;
use App\Training\Domain\Prescription\Rir;
use App\Training\Domain\Prescription\Tempo;

/**
 * What an exercise is usually done with, proposed when it is added to a program.
 */
final readonly class ExerciseDefaults
{
    public ?int $targetRir;
    public int $restSeconds;

    public function __construct(public RepRange $repRange, ?int $targetRir, int $restSeconds, public ?Tempo $tempo = null)
    {
        $this->targetRir = Rir::valid($targetRir);
        $this->restSeconds = Rest::valid($restSeconds);
    }
}
