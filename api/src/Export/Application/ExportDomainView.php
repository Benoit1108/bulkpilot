<?php

declare(strict_types=1);

namespace App\Export\Application;

final readonly class ExportDomainView
{
    public function __construct(public string $key, public string $title, public bool $periodFiltered)
    {
    }
}
