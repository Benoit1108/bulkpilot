<?php

declare(strict_types=1);

namespace App\Training\Domain\Prescription;

use App\Training\Domain\Exception\InvalidPrescription;

/**
 * Seconds per phase of a rep: lowering, pause at the bottom, lifting, pause at the top
 * ("3-1-1-0"). X is as fast as possible ("2-0-X-0").
 */
final readonly class Tempo
{
    public string $value;

    public function __construct(string $tempo)
    {
        $phases = preg_split('/\s*[-\/ ]\s*/', strtoupper(trim($tempo)));
        if (false === $phases) {
            $phases = [];
        }
        // "3110" is common shorthand for 3-1-1-0.
        if (1 === \count($phases) && 1 === preg_match('/^[0-9X]{4}$/', $phases[0])) {
            $phases = str_split($phases[0]);
        }
        if (4 !== \count($phases) || [] !== array_filter($phases, static fn (string $phase): bool => 1 !== preg_match('/^(X|[0-9]|10)$/', $phase))) {
            throw InvalidPrescription::because('a tempo has four phases of 0 to 10 seconds (or X), such as 3-1-1-0');
        }

        $this->value = implode('-', $phases);
    }
}
