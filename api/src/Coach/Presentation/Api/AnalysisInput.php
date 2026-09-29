<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

final class AnalysisInput
{
    /** The device's today (YYYY-MM-DD). */
    public ?string $today = null;

    /** Run it again even though it ran today ("Relancer l'analyse"). */
    public bool $again = false;
}
